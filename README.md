# RabbitMQDemo

A Symfony project demonstrating the use of RabbitMQ.

---

## 1) Requirements
1. Docker
2. Docker Compose
3. Insomnia (or something similar to test POST requests)
4. (Windows) WSL2

---

## 2) Installation / Run
1. Clone the Repository
   ```
   git clone https://github.com/LuigiG34/RedisDemo
   cd RedisDemo
   ```

2. Start Docker Containers
   ```
   docker compose up -d --build
   ```

3. Install PHP Dependencies
   ```
   docker compose exec app composer install
   ```

4. Create Database
   ```
   docker compose exec app php bin/console doctrine:database:create
   ```

5. Generate Database Migration
   ```
   docker compose exec app php bin/console make:migration
   ```

6. Apply Database Migration
   ```
   docker compose exec php php bin/console doctrine:migrations:migrate
   ```

7. Dispatch emails with Insomnia

    Send **POST** request to `http://localhost:8000/emails/send`
   ```json
   [
      { "recipient":"you@mail.com", "subject":"LOW",  "body":"...", "priority":1 },
      { "recipient":"you@mail.com", "subject":"NORM", "body":"...", "priority":2 },
      { "recipient":"you@mail.com", "subject":"HIGH", "body":"...", "priority":3 }
   ]
   ```

8. Handle & proccess emails
   ```
   docker compose exec php php bin/console messenger:consume async_high async async_low -vv
   ```
*We process messages from priority high → normal → low priority*

9. Run tests + Create DB for tests (Optionnal)
   ```
   docker compose exec app php bin/console doctrine:database:create --env=test
   docker compose exec app php ./bin/phpunit --testdox
   ```