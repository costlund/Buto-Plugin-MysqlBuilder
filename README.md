# Buto-Plugin-MysqlBuilder
Methods to handle mysql queries with minimal of settings. Support select, insert and update. Uses schema file and a table name to handle data.


<!-- ## Data
```
$data = array('id' => '_my_id_string_', 'name' => '_a_name_')
``` -->


## Object
```
wfPlugin::includeonce('mysql/builder');
$builder = new PluginMysqlBuilder();
```

## set_schema_file()
Set schema.
```
$builder->set_schema_file('/_path_/_to_/_schema_/schema.yml');
```
Or for multiple schemas.
```
$builder->set_schema_file(array('/_path_/_to_/_schema_/schema.yml'));
```

## set_table_name()
Set table.
```
$builder->set_table_name('_table_name_');
```


## PluginWfMysql, object
Create object and open with your settings.
```
wfPlugin::includeonce('wf/mysql');
$mysql = new PluginWfMysql();
$mysql->open($this->settings->get('_mysql_settings_'));
```


## Critera
Pass any params who are in schema to select from table. 
```
$criteria = new PluginWfArray();
```
```
$criteria->set('where/_table._id/value', '_my_id_string_');
```
With NOT.
```
$criteria->set('where/_table._id/value', '_my_id_string_');
$criteria->set('where/_table_.id/not', true);
```
With isnull.
```
$criteria->set('where/_table_.id/isnull', true);
```

### Operator
Default operator is = but can be changed.
```
$criteria->set('where/_table_.id/operator', ">=");
```

## get_sql_select()
```
$sql_select = $builder->get_sql_select($criteria->get());
```

## PluginWfMysql, execute()

```
$mysql->execute($sql_select);
$rs = $mysql->getOne();
```


## Settings

### join
```
join:
  -
    field: customer_id
```
One could rename table name.
```
join:
  -
    field: customer_id
    table_name_as: TEST
```

### where
```
account.id:
  value: _any_value_
```
### order_by
```
-
  field: account.email
  desc: true
```
### select
Add extra select field.
```
select:
  -
    sql: "(8) as extra_value"
    label: extra value
```
### select_filter
Restrict fields. Useful to get low data usage.
```
select_filter:
  - account.id
  - account.email
```

### select_separator
If using output as json one should replace dot separator to other character.
```
$builder->set_select_separator('_');
```

## get_sql_insert()
Insert data.
```
$sql_insert = $builder->get_sql_insert($data);
$mysql->execute($sql_insert);
```

## get_sql_update()
Uppdate database.

### Description
```
$sql_update = $builder->get_sql_update($data);
$mysql->execute($sql_update);
```
### Parameters
- **data** - Criteria field
- **functions(optional)** - Tells whitch values are sql functions.

### Return values
- **array** - Sql criteria.


### Example
Second param is if values are sql function. In this example param date is a function.
```
$sql_update = $builder->get_sql_update($data, array('date'));
$mysql->execute($sql_update);
```



## Schema
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
