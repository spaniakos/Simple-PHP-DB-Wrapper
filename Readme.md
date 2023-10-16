This is a very simple Database wrapper for PHP and mysql to get you started

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

TODO:
- Add .env to project
- Make it a real php package