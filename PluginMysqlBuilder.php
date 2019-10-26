<?php
class PluginMysqlBuilder{
  public $schema_file = null;
  public $schema_data = null;
  public $table_name = null;
  public $table_name_as = null;
  public $table_data = null;
  private $fields = null;
  private $select = array();
  private $join = null;
  function __construct() {
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
  }
  public function set_schema_file($schema_file){
    $this->schema_file = $schema_file;
    $this->schema_data = new PluginWfYml($this->schema_file);
    if(sizeof($this->schema_data->get())==0){
      throw new Exception('PluginMysqlBuilder says: Could not find data in schema '.$this->schema_file.'.');
    }
    return $this->schema_data;
  }
  public function set_table_name($table_name, $table_name_as = null){
    $this->table_name = $table_name;
    $this->table_name_as = $table_name_as;
    $this->table_data = new PluginWfArray($this->schema_data->get('tables/'.$this->table_name));
    if(!is_array($this->table_data->get())){
      throw new Exception('PluginMysqlBuilder says: Could not find fields for table '.$this->table_name.' in schema '.$this->schema_file.'.');
    }
    if($this->schema_data->get('extra/field') && is_array($this->table_data->get('field'))){
      $this->table_data->set('field', array_merge($this->table_data->get('field'), $this->schema_data->get('extra/field')));
    }
    return null;
  }
  public function get_sql_insert($data){
    $sql = new PluginWfArray();
    $str = 'insert into '.$this->table_name.' ([fields]) values ([values]);';
    $this->fields = '';
    $values = '';
    foreach ($this->table_data->get('field') as $key => $value) {
      if(isset($data[$key])){
        $this->fields .= "$key,";
        $values .= "?,";
      }
    }
    $str = str_replace('[fields]', substr($this->fields, 0, strlen($this->fields)-1), $str);
    $str = str_replace('[values]', substr($values, 0, strlen($values)-1), $str);
    $sql->set('sql', $str);
    foreach ($this->table_data->get('field') as $key => $value) {
      if(isset($data[$key])){
        $sql->set('params/', array('type' => $value['type'], 'value' => $data[$key]));
      }
    }
    return $sql->get();
  }
  public function get_sql_update($data){
    $sql = new PluginWfArray();
    $str = 'update '.$this->table_name.' set [fields] where [where];';
    $this->fields = '';
    $where = '';
    $this->fields_params = array();
    $where_params = array();
    foreach ($this->table_data->get('field') as $key => $value) {
      $i = new PluginWfArray($value);
      if(isset($data[$key])){
        if(!$i->get('primary_key')){
          $this->fields .= "$key=?,";
          $this->fields_params[] = array('type' => $value['type'], 'value' => $data[$key]);
        }else{
          $where .= "$key=? and ";
          $where_params[] = array('type' => $value['type'], 'value' => $data[$key]);
        }
      }
    }
    $str = str_replace('[fields]', substr($this->fields, 0, strlen($this->fields)-1), $str);
    $str = str_replace('[where]', substr($where, 0, strlen($where)-5), $str);
    $sql->set('sql', $str);
    $sql->set('params', array_merge($this->fields_params, $where_params));
    return $sql->get();
  }
  private function set_join($data){ //$field, $table_name, $foreing_key){
    $foreign_key = new PluginWfArray($data->get('foreign_key'));
    /**
     * 
     */
    if($data->get('table_name_join_as')){
      $data->set('table_name', $data->get('table_name_join_as'));
    }
    /**
     * 
     */
    if(!$data->get('table_name_as')){
      $this->join .= 'left join '.$foreign_key->get('reference_table').' on '.$data->get('table_name').'.'.$data->get('field').'='.$foreign_key->get('reference_table').'.'.$foreign_key->get('reference_field').' ';
    }else{
      $this->join .= 'left join '.$foreign_key->get('reference_table').' as '.$data->get('table_name_as').' on '.$data->get('table_name').'.'.$data->get('field').'='.$data->get('table_name_as').'.'.$foreign_key->get('reference_field').' ';
    }
    return null;
  }
  private function set_field($data){
    if(!$data->get('table_name_as')){
      foreach ($data->get('foreign_key/field') as $key => $value) {
        $i = new PluginWfArray($value);
        $this->fields .= $data->get('foreign_key/reference_table').".$key,";
        $this->select[] = $data->get('foreign_key/reference_table').'.'.$key;
      }
    }else{
      foreach ($data->get('foreign_key/field') as $key => $value) {
        $i = new PluginWfArray($value);
        $this->fields .= $data->get('table_name_as').".$key,";
        $this->select[] = $data->get('table_name_as').'.'.$key;
      }
    }
    return null;
  }
  public function get_sql_select($criteria = array()){
    $criteria = new PluginWfArray($criteria);
    $sql = new PluginWfArray();
    $this->fields = '';
    $params = array();
    $this->select = array();
    if(!$this->table_name_as){
      $str = 'select [fields] from '.$this->table_name.' [join] where 1=1;';
    }else{
      $str = 'select [fields] from '.$this->table_name.' as '.$this->table_name_as.' [join] where 1=1;';
    }
    /**
     * Field
     */
    $temp = new PluginWfArray();
    $temp->set('foreign_key/reference_table', $this->table_name);
    $temp->set('table_name_as', $this->table_name_as);
    $temp->set('foreign_key/field', $this->table_data->get('field'));
    $this->set_field($temp);
    /**
     * Join
     */
    $this->join = null;
    if($criteria->get('join')){
      foreach ($criteria->get('join') as $value_1) {
        $i_1 = new PluginWfArray($value_1);
        $i_1->set('table_name', $this->table_name);
        $i_1->set('table_name_join_as', $this->table_name_as);
        $i_1->set('foreign_key', $this->schema_data->get('tables/'.$i_1->get('table_name').'/field/'.$i_1->get('field').'/foreing_key'));
        $i_1->set('foreign_key/field', $this->schema_data->get('tables/'.$i_1->get('foreign_key/reference_table').'/field'));
        $this->set_join($i_1);
        $this->set_field($i_1);
        if($i_1->get('join')){
          foreach ($i_1->get('join') as $value_2) {
            $i_2 = new PluginWfArray($value_2);
            $i_2->set('table_name', $i_1->get('foreign_key/reference_table'));
            $i_2->set('table_name_join_as', $i_1->get('table_name_as'));
            $i_2->set('foreign_key', $this->schema_data->get('tables/'.$i_2->get('table_name').'/field/'.$i_2->get('field').'/foreing_key'));
            $i_2->set('foreign_key/field', $this->schema_data->get('tables/'.$i_2->get('foreign_key/reference_table').'/field'));
            $this->set_join($i_2);
            $this->set_field($i_2);
            if($i_2->get('join')){
              foreach ($i_2->get('join') as $value_3) {
                $i_3 = new PluginWfArray($value_3);
                $i_3->set('table_name', $i_2->get('foreign_key/reference_table'));
                $i_3->set('table_name_join_as', $i_2->get('table_name_as'));
                $i_3->set('foreign_key', $this->schema_data->get('tables/'.$i_3->get('table_name').'/field/'.$i_3->get('field').'/foreing_key'));
                $i_3->set('foreign_key/field', $this->schema_data->get('tables/'.$i_3->get('foreign_key/reference_table').'/field'));
                $this->set_join($i_3);
                $this->set_field($i_3);
              }
            }
          }
        }
      }
    }
    /**
     * Where
     */
    $criteria_where = $criteria->get('where');
    $where = null;
    foreach ($this->table_data->get('field') as $key => $value) {
      $i = new PluginWfArray($value);
      if(isset($criteria_where[$key])){
        $where .= $this->table_name.".$key=? and ";
        $params[] = array('type' => $value['type'], 'value' => $criteria_where[$key]);
      }
    }
    /**
     * Replace
     */
    $str = str_replace('[fields]', substr($this->fields, 0, strlen($this->fields)-1), $str);
    $str = str_replace('[join]', $this->join, $str);
    if($where){
      $str = str_replace('1=1', substr($where, 0, strlen($where)-5), $str);
    }else{
      $str = str_replace('where 1=1', '', $str);
    }
    /**
     * Set data
     */
    $sql->set('sql', $str);
    $sql->set('params', $params);
    $sql->set('select', $this->select);
    return $sql->get();
  }
}
