This is a very simple Database wrapper for PHP and mysql to get you started
Simple and lightwait, makes it ideal for quick projects.

Requirements:
```
php 8.2
composer
```

install:

composer install

Usage:

Change the credentials inside .env
You can copy the env.sample to .env 
Require the class from a php file

```
DB_HOST=your_database_host
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=your_database_name
DB_PORT=3306
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_0900_ai_ci
LOG_PATH=path/to/your/logfile.log
```
How to include it in a file:
```
require_once('database.class.php');
$db = new Database();
```

How to call the function (sample):

Raw query
```
$query = 'select * from table;
$rs = $db->Query($query);
```

update sample:
UpdateTable($table,$set,$where= ''){
```
$db->UpdateTable("table","column='data',column2=1","column3='where' and column4=2");
```

GetAllFromTable($table,$where = '',$order_col = 'ID',$order = 'ASC', $limit = '') {
```
$rs = $db->GetAllFromTable("table","username='user1' and password='password'",'id','asc', 1000) {
```

Iterate the results:
```
foreach($rs as $data){
    //do
}
```

TODO:
- Make it a real php package
