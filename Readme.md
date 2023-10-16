This is a very simple Database wrapper for PHP and mysql to get you started
Simple and lightwait, makes it ideal for quick projects.

Usage:

Change the credentials inside the database.class.php
Require the class from a php file

```
    protected $host="localhost";
    protected $user="user";
    protected $db="database";
    protected $pass="password";
    protected $port="3306";
    protected $conn;
    protected $charset="utf8mb4";
    protected $collation="utf8mb4_0900_ai_ci";
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
- Add .env to project
- Make it a real php package