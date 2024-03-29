<?php
class PluginMysqlBuilder{
  public $schema_file = null;
  public $schema_data = null;
  public $table_name = null;
  public $table_name_as = null;
  public $table_data = null;
  private $fields = null;
  private $fields_params = array();
  private $where = null;
  private $select = array();
  private $select_separator = null;
  private $join = '';
  function __construct() {
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
  }
  public function set_select_separator($select_separator){
    $this->select_separator = $select_separator;
  }
  public function set_schema_file($schema_file){
    /**
     * Handle input.
     */
    if(!is_array($schema_file)){
      $this->schema_file = array($schema_file);
    }else{
      $this->schema_file = $schema_file;
    }
    /**
     * Read data files and merge.
     */
    $tables = array();
    foreach ($this->schema_file as $key => $value) {
      $temp = new PluginWfYml(wfGlobals::getAppDir().$value);
      if(sizeof($temp->get())==0){
        throw new Exception('PluginMysqlBuilder says: Could not find data in schema '.$value.'.');
      }
      if($temp->get('extra/field')){
        foreach ($temp->get('tables') as $key => $value) {
          $temp->set("tables/$key/field", array_merge($temp->get("tables/$key/field"), $temp->get('extra/field')));
        }
      }
      $tables = array_merge($tables, $temp->get('tables'));
    }
    /**
     * Set schema data.
     */
    $this->schema_data = new PluginWfArray(array('tables' => $tables));
    /**
     * 
     */
    return $this->schema_data;
  }
  public function set_table_name($table_name, $table_name_as = null){
    $this->table_name = $table_name;
    $this->table_name_as = $table_name_as;
    $this->table_data = new PluginWfArray($this->schema_data->get('tables/'.$this->table_name));
    if(!is_array($this->table_data->get())){
      throw new Exception('PluginMysqlBuilder says: Could not find fields for table '.$this->table_name.' in schema.');
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
    /**
     * Created by
     * If exist in schema and not included.
     */
    if($this->table_data->get('field/created_by') && !isset($data['created_by'])){
      $this->fields .= "created_by,";
      $values .= "'[user_id]',";
    }
    /**
     * 
     */
    $str = wfPhpfunc::str_replace('[fields]', wfPhpfunc::substr($this->fields, 0, wfPhpfunc::strlen($this->fields)-1), $str);
    $str = wfPhpfunc::str_replace('[values]', wfPhpfunc::substr($values, 0, wfPhpfunc::strlen($values)-1), $str);
    $sql->set('sql', $str);
    foreach ($this->table_data->get('field') as $key => $value) {
      if(isset($data[$key])){
        $sql->set('params/', array('type' => $value['type'], 'value' => $data[$key]));
      }
    }
    return $sql->get();
  }
  public function get_sql_delete($data){
    $sql = new PluginWfArray();
    $str = 'delete from '.$this->table_name.' where [where];';
    $this->where = '';
    foreach ($this->table_data->get('field') as $key => $value) {
      if(array_key_exists($key, $data)){
        $this->where .= "$key=? and ";
      }
    }
    $str = wfPhpfunc::str_replace('[where]', wfPhpfunc::substr($this->where, 0, wfPhpfunc::strlen($this->where)-5), $str);
    $sql->set('sql', $str);
    foreach ($this->table_data->get('field') as $key => $value) {
      if(array_key_exists($key, $data)){
        $sql->set('params/', array('type' => $value['type'], 'value' => $data[$key]));
      }
    }
    return $sql->get();
  }
  /**
   * SQL for update.
   * @param array $data Critera field.
   * @param array $function Tells whitch values are sql functions.
   * @return array Sql criteria.
   */
  public function get_sql_update($data, $functions = array()){
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
          if(array_search($key, $functions)===false){
            $this->fields .= "$key=?,";
            $this->fields_params[] = array('type' => $value['type'], 'value' => $data[$key]);
          }else{
            $this->fields .= "$key=".$data[$key].",";
          }
        }else{
          $where .= "$key=? and ";
          $where_params[] = array('type' => $value['type'], 'value' => $data[$key]);
        }
      }
    }
    $str = wfPhpfunc::str_replace('[fields]', wfPhpfunc::substr($this->fields, 0, wfPhpfunc::strlen($this->fields)-1), $str);
    $str = wfPhpfunc::str_replace('[where]', wfPhpfunc::substr($where, 0, wfPhpfunc::strlen($where)-5), $str);
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
      /**
       * 
       */
      $data->set('table_name', $data->get('table_name_join_as'));
    }
    /**
     * 
     */
    if(!$data->get('table_name_as')){
      $this->join .= 'left join '.$foreign_key->get('reference_table').' on '.$data->get('table_name').'.'.$data->get('field').'='.$foreign_key->get('reference_table').'.'.$foreign_key->get('reference_field').' ';
    }else{
      /**
       * Add to schema.
       */
      $this->add_to_schema($data->get('table_name_as'), $foreign_key->get('reference_table'));
      /**
       * 
       */
      $this->join .= 'left join '.$foreign_key->get('reference_table').' as '.$data->get('table_name_as').' on '.$data->get('table_name').'.'.$data->get('field').'='.$data->get('table_name_as').'.'.$foreign_key->get('reference_field').' ';
    }
    return null;
  }
  /**
   * Sets $this->fields and $this->select.
   * @param PluginWfArray $data
   * @return null
   */
  private function set_field($data, $join = false){
    $join_prefix = null;
    if($join){
      $join_prefix = 'j_';
    }
    $table_name = $data->get('foreign_key/reference_table');
    if($data->get('table_name_as')){
      $table_name = $data->get('table_name_as');
    }
    foreach ($data->get('foreign_key/field') as $key => $value) {
      $i = new PluginWfArray($value);
      $this->fields .= $table_name.".$key,";
      if(!$join){
        $this->select[] = $key;
      }else{
        $this->select[] = $join_prefix.$table_name.'.'.$key;
      }
    }
    return null;
  }
  /**
   * Set $this->schema_data a copy of other table_name to handle when table_name_as is set.
   * @param string $table_name_as
   * @param string $table_name
   * @return null
   */
  private function add_to_schema($table_name_as, $table_name){
    /**
     * Add to schema.
     */
    $this->schema_data->set('tables/'.$table_name_as, $this->schema_data->get('tables/'.$table_name));
    return null;
  }
  public function get_sql_select($criteria = array()){
    $criteria = new PluginWfArray($criteria);
    $sql = new PluginWfArray();
    $this->fields = '';
    $params = array();
    $this->select = array();
    if(!$this->table_name_as){
      $str = 'select [fields] from '.$this->table_name.' [join] where 1=1 [order_by];';
    }else{
      /**
       * Add to schema.
       */
      $this->add_to_schema($this->table_name_as, $this->table_name);
      /**
       * 
       */
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
    unset($temp);
    /**
     * Join
     */
    $this->join = '';
    if($criteria->get('join')){
      foreach ($criteria->get('join') as $value_1) {
        $i_1 = new PluginWfArray($value_1);
        $i_1->set('table_name', $this->table_name);
        $i_1->set('table_name_join_as', $this->table_name_as);
        $i_1->set('foreign_key', $this->schema_data->get('tables/'.$i_1->get('table_name').'/field/'.$i_1->get('field').'/foreing_key'));
        $i_1->set('foreign_key/field', $this->schema_data->get('tables/'.$i_1->get('foreign_key/reference_table').'/field'));
        $this->set_join($i_1);
        $this->set_field($i_1, true);
        if($i_1->get('join')){
          foreach ($i_1->get('join') as $value_2) {
            $i_2 = new PluginWfArray($value_2);
            $i_2->set('table_name', $i_1->get('foreign_key/reference_table'));
            $i_2->set('table_name_join_as', $i_1->get('table_name_as'));
            $i_2->set('foreign_key', $this->schema_data->get('tables/'.$i_2->get('table_name').'/field/'.$i_2->get('field').'/foreing_key'));
            $i_2->set('foreign_key/field', $this->schema_data->get('tables/'.$i_2->get('foreign_key/reference_table').'/field'));
            $this->set_join($i_2);
            $this->set_field($i_2, true);
            if($i_2->get('join')){
              foreach ($i_2->get('join') as $value_3) {
                $i_3 = new PluginWfArray($value_3);
                $i_3->set('table_name', $i_2->get('foreign_key/reference_table'));
                $i_3->set('table_name_join_as', $i_2->get('table_name_as'));
                $i_3->set('foreign_key', $this->schema_data->get('tables/'.$i_3->get('table_name').'/field/'.$i_3->get('field').'/foreing_key'));
                $i_3->set('foreign_key/field', $this->schema_data->get('tables/'.$i_3->get('foreign_key/reference_table').'/field'));
                $this->set_join($i_3);
                $this->set_field($i_3, true);
              }
            }
          }
        }
      }
    }
    /**
     * select_filter
     */
    if($criteria->get('select_filter')){
      /**
       * 
       */
      $this->fields = '';
      foreach($criteria->get('select_filter') as $v){
        $this->fields .= $v.',';
      }
      /**
       * 
       */
      $this->select = $criteria->get('select_filter');
    }
    /**
     * Where
     */
    $criteria_where = $criteria->get('where');
    $where = null;
    if($criteria_where){
      foreach ($criteria_where as $key => $value){
        $i = new PluginWfArray($value);
        $x = explode('.', $key);
        $temp = new PluginWfArray($this->schema_data->get('tables/'.$x[0].'/field/'.$x[1]  ));
        /**
         * NOT
         */
        $not = null;
        if($i->get('not')){
          $not = 'NOT ';
        }
        /**
         * Operator
         */
        $operator = "=";
        if($i->get('operator')){
          $operator = $i->get('operator');
        }
        /**
         *
         */
        if(!$i->get('isnull')){
          /**
           * Normal where
           */
          $where .= "$not$key $operator ? and ";
          $params[] = array('type' => $temp->get('type'), 'value' => $i->get('value'));
        }else{
          /**
           * isnull
           */
          $where .= "isnull($key) and ";
        }
      }
    }
    /**
     * Order by
     */
    $order_by = null;
    if($criteria->get('order_by')){
      foreach ($criteria->get('order_by') as $value) {
        $v = new PluginWfArray($value);
        if(!$v->get('value')){
          $order_by .= ','.$v->get('field');
        }else{
          $order_by .= ','.$v->get('field')."='".$v->get('value')."'";
        }
        if($v->get('desc')){
          $order_by .= ' desc';
        }
      }
      $order_by = 'order by '.substr($order_by, 1);
    }
    /**
     * Select extra.
     */
    if($criteria->get('select')){
      foreach ($criteria->get('select') as $value) {
        $this->fields .= $value['sql'].',';
        $this->select[] = $value['label'];
      }
    }
    /**
     * Replace
     */
    $str = wfPhpfunc::str_replace('[fields]', wfPhpfunc::substr($this->fields, 0, wfPhpfunc::strlen($this->fields)-1), $str);
    $str = wfPhpfunc::str_replace('[join]', $this->join, $str);
    $str = wfPhpfunc::str_replace('[order_by]', $order_by, $str);
    if($where){
      $str = wfPhpfunc::str_replace('1=1', wfPhpfunc::substr($where, 0, wfPhpfunc::strlen($where)-5), $str);
    }else{
      $str = wfPhpfunc::str_replace('where 1=1', '', $str);
    }
    /**
     * Set data
     */
    $sql->set('sql', $str);
    $sql->set('params', $params);
    $sql->set('select', $this->select);
    /**
     * Select separator
     */
    if($this->select_separator){
      $temp = array();
      foreach ($sql->get('select') as $key => $value) {
        $temp[] = wfPhpfunc::str_replace('.', $this->select_separator, $value);
      }
      $sql->set('select', $temp);
    }
    /**
     * 
     */
    return $sql->get();
  }
}
