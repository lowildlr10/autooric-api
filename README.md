## Installation

### Create .env file
- Run this command "cp .env.example .env" or simple create a copy of .env.example file and rename it into .env
- Update the necessary variables in the .env file: <br/><br />
  ```
  APP_NAME=
  APP_DEBUG=
  APP_URL=
  DB_HOST=
  DB_PORT=
  DB_DATABASE=
  DB_USERNAME=
  DB_PASSWORD=

  ...etc
  ```

### Run this commands
```
composer install

composer dump-autoload

php artisan key:generate

php artisan migrate:fresh --seed

php artisan cache:clear

php artisan config:clear
```

### Test the system
- Test using the admin account
```
Username: admin
Password: pwd12345
```
