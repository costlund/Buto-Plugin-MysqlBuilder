# Buto-Plugin-MysqlBuilder
Methods to handle mysql queries with minimal of settings. Support select, insert and update. Uses schema file and a table name to handle data.

## Usage
Example.
### Data
```
$data = array('id' => '_my_id_string_', 'name' => '_a_name_')
```

### PluginMysqlBuilder
Set schema and table.
```
wfPlugin::includeonce('mysql/builder');
$builder = new PluginMysqlBuilder();
$builder->set_schema_file('/_path_/_to_/_schema_/schema.yml');
$builder->set_table_name('_table_name_');
```

### PluginWfMysql
Create object and open with your settings.
```
wfPlugin::includeonce('wf/mysql');
$mysql = new PluginWfMysql();
$mysql->open($this->settings->get('_mysql_settings_'));
```

### Select
Pass any params who are in schema to select from table. 
```
$criteria = new PluginWfArray();
$criteria->set('where/id', '_my_id_string_');

$sql_select = $builder->get_sql_select($criteria->get());
$mysql->execute($sql_select);
$rs = $mysql->getOne();
```

### Insert
Insert data.
```
$sql_insert = $builder->get_sql_insert($data);
$mysql->execute($sql_insert);
```

### Update
Update data.
```
$sql_update = $builder->get_sql_update($data);
$mysql->execute($sql_update);
```

### Schema
For this example a schema should look like this.
```
tables:
  _table_name_:
    field:
      id:
        type: varchar(50)
        not_null: true
        primary_key: true
      name:
        type: varchar(255)
```
