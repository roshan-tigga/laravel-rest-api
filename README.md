## Project Title

LMS

## Project Description

LMS

## Project Requirements

PHP Version - 8.0

Laravel Version - 9.0


## Project Setup

Step 1 - Clone the repository

Now you need to choose the Driver and Open CMD and past this url (git clone https://roshantigga@bitbucket.org/staginglms/lms.git) and run.

Step 2 - Run command "composer update" inside your project root.

Step 3 - Copy of the .env.example file and rename it to .env inside your project root.

Step 4 - Open your .env file and change the database name (DB_DATABASE) to whatever you have, username (DB_USERNAME) and password (DB_PASSWORD) field correspond to your configuration.

Step 5 - Generate a new application key, Run command "php artisan key:generate" inside your project root.

Step 6 - Generate a new access tokens, Run command "php artisan passport:install" inside your project root.

Step 7 - Start the local development server, Run command "php artisan serve" inside your project root.

Step 8 - You can now access the server at http://localhost:8000/ OR http://127.0.0.1:8000


## Project Packages

Redis Configuration

Step 1 - Open your .env file and change the REDIS_HOST, REDIS_PASSWORD and REDIS_PORT field correspond to your configuration.


S3 Bucket Configuration

Step 1 - Open your .env file and change the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION and AWS_BUCKET field correspond to your configuration.




