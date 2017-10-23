<?php
/**
 * Writes page hits to Sqlite table. Set up the event and then run it as a page plugin to /.../start.
 */
class PluginPageCounter_v1{
  private $filename = null;
  private $sqlite = null;
  function __construct($buto) {
    if($buto){
      $this->filename = wfArray::get($GLOBALS, 'sys/theme_data_dir').'/plugin_page_counter_v1.db';
      wfPlugin::includeonce('wf/sqlite3_v1');
      $this->sqlite = new PluginWfSqlite3_v1();
      $this->sqlite->filename = $this->filename;
    }
  }
  /**
   * Create table.
   */
  private function create_table(){
    $sql = "CREATE TABLE page
            (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id varchar(255),
            HTTP_HOST varchar(255),
            HTTP_USER_AGENT varchar(255),
            HTTP_REFERER varchar(255),
            HTTP_COOKIE text,
            REMOTE_ADDR varchar(255),
            REQUEST_URI varchar(255),
            theme varchar(255),
            class varchar(255),
            method varchar(255),
            language varchar(255),
            created_at timestamp NULL default CURRENT_TIMESTAMP
            )";
    $this->sqlite->exec($sql);
  }
  /**
   * Run this event on document_render_before.
   */
  public function event_count(){
    /**
     * Check if db exist.
     */
    if(!wfFilesystem::fileExist($this->filename)){
      $this->sqlite->open();
      $this->create_table();
    }else{
      $this->sqlite->open();
    }
    /**
     * Save.
     */
    if(wfArray::get($GLOBALS, 'sys/plugin') != 'page/counter_v1'){
      wfPlugin::includeonce('wf/array');
      $server = new PluginWfArray($_SERVER);
      $this->sqlite->exec("insert into page (session_id,HTTP_HOST,HTTP_USER_AGENT,HTTP_REFERER,HTTP_COOKIE,REMOTE_ADDR,REQUEST_URI,theme,class,method,language) values ('".session_id()."','".$server->get('HTTP_HOST')."','".$server->get('HTTP_USER_AGENT')."','".$server->get('HTTP_REFERER')."','".$server->get('HTTP_COOKIE')."','".$server->get('REMOTE_ADDR')."','".$server->get('REQUEST_URI')."','".wfArray::get($GLOBALS, 'sys/theme')."','".wfArray::get($GLOBALS, 'sys/class')."','".wfArray::get($GLOBALS, 'sys/method')."','".wfI18n::getLanguage()."')");
    }
    return null;
  }
  private function init_page(){
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
    wfPlugin::enable('datatable/datatable_1_10_13');
    wfPlugin::enable('datatable/datatable_1_10_16');
    wfArray::set($GLOBALS, 'sys/layout_path', '/plugin/page/counter_v1/layout');
    if(!wfUser::hasRole("webmaster") && !wfUser::hasRole("databasemaster") && !wfUser::hasRole("webadmin")){
      exit('Role webmaster, webadmin or databasemaster is required!');
    }
    if(!wfFilesystem::fileExist($this->filename)){
      echo 'File '.$this->filename.' does not exist.';
    }
  }
  /**
   * Start page.
   */
  public function page_start(){
    $this->init_page();
    $page = $this->getYml('page/start.yml');
    wfDocument::mergeLayout($page->get());
  }
  public function page_list_all(){
    $this->init_page();
    $this->sqlite->open();
    $rs = $this->sqlite->query("select id, session_id, HTTP_HOST, HTTP_USER_AGENT, HTTP_COOKIE, REMOTE_ADDR, HTTP_REFERER, REQUEST_URI, theme, class, method, language, datetime(created_at, 'localtime') as created_at from page order by created_at desc;");
    $tr = array();
    foreach ($rs as $key => $value){
      $item = new PluginWfArray($value);
      $tr[] = wfDocument::createHtmlElement('tr', array(
          wfDocument::createHtmlElement('td', $item->get('id')),
          wfDocument::createHtmlElement('td', $item->get('session_id')),
          wfDocument::createHtmlElement('td', $item->get('HTTP_HOST')),
          wfDocument::createHtmlElement('td', $item->get('HTTP_USER_AGENT')),
          wfDocument::createHtmlElement('td', $item->get('HTTP_COOKIE')),
          wfDocument::createHtmlElement('td', array($this->getRemoteAddrLink($item->get('REMOTE_ADDR')))),
          wfDocument::createHtmlElement('td', $item->get('HTTP_REFERER')),
          wfDocument::createHtmlElement('td', $item->get('REQUEST_URI')),
          wfDocument::createHtmlElement('td', $item->get('theme')),
          wfDocument::createHtmlElement('td', $item->get('class')),
          wfDocument::createHtmlElement('td', $item->get('method')),
          wfDocument::createHtmlElement('td', $item->get('language')),
          wfDocument::createHtmlElement('td', date('ymd H:i', strtotime($item->get('created_at'))))
          ));
    }
    $page = $this->getYml('page/list_all.yml');
    $page->setById('tbody', 'innerHTML', $tr);
    wfDocument::mergeLayout($page->get());
  }
  private function getRemoteAddrLink($REMOTE_ADDR){
    return wfDocument::createHtmlElement('a', $REMOTE_ADDR, array('href' => "http://whatismyipaddress.com/ip/$REMOTE_ADDR", 'target' => '_blank'));
  }
  public function page_list_group_by_ip(){
    $this->init_page();
    $this->sqlite->open();
    $rs = $this->sqlite->query("select REMOTE_ADDR, count(id) as hits from page group by REMOTE_ADDR;");
    $tr = array();
    foreach ($rs as $key => $value){
      $item = new PluginWfArray($value);
      $tr[] = wfDocument::createHtmlElement('tr', array(
          wfDocument::createHtmlElement('td', array($this->getRemoteAddrLink($item->get('REMOTE_ADDR')))),
          wfDocument::createHtmlElement('td', $item->get('hits'))
          ));
    }
    $page = $this->getYml('page/list_group_by_ip.yml');
    $page->setById('tbody', 'innerHTML', $tr);
    wfDocument::mergeLayout($page->get());
  }
  public function page_list_group_by_page(){
    $this->init_page();
    $this->sqlite->open();
    $rs = $this->sqlite->query("select class, method, count(id) as hits from page group by class, method;");
    $tr = array();
    foreach ($rs as $key => $value){
      $item = new PluginWfArray($value);
      $tr[] = wfDocument::createHtmlElement('tr', array(
          wfDocument::createHtmlElement('td', $item->get('class')),
          wfDocument::createHtmlElement('td', $item->get('method')),
          wfDocument::createHtmlElement('td', $item->get('hits'))
          ));
    }
    $page = $this->getYml('page/list_group_by_page.yml');
    $page->setById('tbody', 'innerHTML', $tr);
    wfDocument::mergeLayout($page->get());
  }
  public function page_list_group_by_day(){
    $this->init_page();
    $this->sqlite->open();
    $rs = $this->sqlite->query("select substr(datetime(created_at, 'localtime'), 1, 10) as day, count(id) as hits from page group by day;");
    $tr = array();
    foreach ($rs as $key => $value){
      $item = new PluginWfArray($value);
      $tr[] = wfDocument::createHtmlElement('tr', array(
          wfDocument::createHtmlElement('td', $item->get('day')),
          wfDocument::createHtmlElement('td', $item->get('hits'))
          ));
    }
    $page = $this->getYml('page/list_group_by_day.yml');
    $page->setById('tbody', 'innerHTML', $tr);
    wfDocument::mergeLayout($page->get());
  }
  public function page_list_group_by_day_and_ip(){
    $this->init_page();
    $this->sqlite->open();
    $rs = $this->sqlite->query("select substr(datetime(created_at, 'localtime'), 1, 10) as day, REMOTE_ADDR, count(id) as hits from page group by day, REMOTE_ADDR;");
    $tr = array();
    foreach ($rs as $key => $value){
      $item = new PluginWfArray($value);
      $tr[] = wfDocument::createHtmlElement('tr', array(
          wfDocument::createHtmlElement('td', $item->get('day')),
          wfDocument::createHtmlElement('td', array($this->getRemoteAddrLink($item->get('REMOTE_ADDR')))),
          wfDocument::createHtmlElement('td', $item->get('hits'))
          ));
    }
    $page = $this->getYml('page/list_group_by_day_and_ip.yml');
    $page->setById('tbody', 'innerHTML', $tr);
    wfDocument::mergeLayout($page->get());
  }
  private function getYml($file){
    return new PluginWfYml(wfArray::get($GLOBALS, 'sys/app_dir').'/plugin/page/counter_v1/'.$file);
  }
}





















