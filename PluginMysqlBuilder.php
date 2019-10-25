<?php
class PluginMysqlBuilder{
  public $schema = null;
  public $table = null;
  function __construct() {
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
  }
  public function set_schema($schema){
    $this->schema = $schema;
  }
  public function set_table($table){
    $this->table = $table;
  }
  private function get_schema_data(){
    $data = new PluginWfYml($this->schema);
    if(sizeof($data->get())==0){
      throw new Exception('PluginMysqlBuilder says: Could not find data in schema '.$this->schema.'.');
    }
    $field = $data->get('tables/'.$this->table.'/field');
    if(!is_array($field)){
      throw new Exception('PluginMysqlBuilder says: Could not find fields for table '.$this->table.' in schema '.$this->schema.'.');
    }
    if($data->get('extra/field') && is_array($field)){
      $field = array_merge($field, $data->get('extra/field'));
    }
    return $field;
  }
  public function get_sql_insert($data){
    $schema = $this->get_schema_data();
    $sql = new PluginWfArray();
    $str = 'insert into '.$this->table.' ([fields]) values ([values]);';
    $fields = '';
    $values = '';
    foreach ($schema as $key => $value) {
      if(isset($data[$key])){
        $fields .= "$key,";
        $values .= "?,";
      }
    }
    $str = str_replace('[fields]', substr($fields, 0, strlen($fields)-1), $str);
    $str = str_replace('[values]', substr($values, 0, strlen($values)-1), $str);
    $sql->set('sql', $str);
    foreach ($schema as $key => $value) {
      if(isset($data[$key])){
        $sql->set('params/', array('type' => $value['type'], 'value' => $data[$key]));
      }
    }
    return $sql->get();
  }
  public function get_sql_update($data){
    $schema = $this->get_schema_data();
    $sql = new PluginWfArray();
    $str = 'update '.$this->table.' set [fields] where [where];';
    $fields = '';
    $where = '';
    $fields_params = array();
    $where_params = array();
    foreach ($schema as $key => $value) {
      $i = new PluginWfArray($value);
      if(isset($data[$key])){
        if(!$i->get('primary_key')){
          $fields .= "$key=?,";
          $fields_params[] = array('type' => $value['type'], 'value' => $data[$key]);
        }else{
          $where .= "$key=? and ";
          $where_params[] = array('type' => $value['type'], 'value' => $data[$key]);
        }
      }
    }
    $str = str_replace('[fields]', substr($fields, 0, strlen($fields)-1), $str);
    $str = str_replace('[where]', substr($where, 0, strlen($where)-5), $str);
    $sql->set('sql', $str);
    $sql->set('params', array_merge($fields_params, $where_params));
    return $sql->get();
  }
  public function get_sql_select($criteria = array()){
    $criteria = new PluginWfArray($criteria);
    $criteria_where = $criteria->get('where');
    $schema = $this->get_schema_data();
    $sql = new PluginWfArray();
    $fields = '';
    $where = null;
    $params = array();
    $select = array();
    $str = 'select [fields] from '.$this->table.' where 1=1;';
    foreach ($schema as $key => $value) {
      $i = new PluginWfArray($value);
      $fields .= "$key,";
      $select[] = $key;
    }
    foreach ($schema as $key => $value) {
      $i = new PluginWfArray($value);
      if(isset($criteria_where[$key])){
        $where .= "$key=? and ";
        $params[] = array('type' => $value['type'], 'value' => $criteria_where[$key]);
      }
    }
    $str = str_replace('[fields]', substr($fields, 0, strlen($fields)-1), $str);
    if($where){
      $str = str_replace('1=1', substr($where, 0, strlen($where)-5), $str);
    }else{
      $str = str_replace('where 1=1', '', $str);
    }
    $sql->set('sql', $str);
    $sql->set('params', $params);
    $sql->set('select', $select);
    return $sql->get();
  }
}
