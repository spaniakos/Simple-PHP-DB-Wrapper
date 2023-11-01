This is a very simple Database wrapper for PHP and mysql to get you started
Simple and lightwait, makes it ideal for quick projects.

You can Import (After publish) via
composer require spaniakos/simple-php-db-wrapper

DO NOT FORGET TO ADD A .env to your project
OR add this to your .env

```
DEBUG=false
DB_HOST="localhost"
DB_USER="root"
DB_PASS="toor"
DB_NAME="test_database"
DB_PORT=3306
DB_CHARSET="utf8mb4"
DB_COLLATION="utf8mb4_general_ci"
LOG_PATH="path of log file with proper permissions"
```
Below are the test config for the tests:
```
DB_CHARSET="utf8mb4"
DB_COLLATION="utf8mb4_general_ci"
```
Please do change the above to your desired valued

Then on your script you need to require vendor autoload IF you havent already somewhere:
require_once 'vendor/autoload.php';

and then you can use the library by using the above line

use Spaniakos\SimplePhpDbWrapper;

Sample usage on a class:
```
<?php declare(strict_types=1);

require_once 'vendor/autoload.php';
use Spaniakos\SimplePhpDbWrapper;

class MyClass {
    protected $db;

    /**
     * Set up the test environment before each test.
     */
    public function __construct() {
        // Create an instance of the Database class before each test
        $this->db = new SimplePhpDbWrapper(true);
    }

    public function testGetColFromTable() {
        $result = $this->db->GetColFromTable('users', 'username', 'id > 0', 'username', '5');
    }
}
```
Requirements:
```
php >= 7.4
composer
```

It uses monolog for logging of error messages from the PDO sql

Dependencies:
```
monolog/monolog: ^3.4
symfony/dotenv: ^6.3
```

Dev Dependencies:
```
friendsofphp/php-cs-fixer: ^3.35
phpunit/phpunit: ^10.4.1
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

Future work:
```
* Change to Depencancy injection from env
* Change unit tests from mysql to mysql-lite for automated testing
* Write better examples
* Stracture the function to use arrays instaid of plain string in order to have better and simpler understanding of the Injecttion methods
* Consider Chainable methods
```
