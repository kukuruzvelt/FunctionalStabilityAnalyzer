# Functional stability analyzer app

### Prerequisites

Before you begin, ensure you have the following installed on your system:
- Docker 25.0.3+
- Docker Compose 2.24.5+
- Git 2.34.1+
- Make 3.75

### Steps

1. **Clone the Repository**

   We recommend using Linux to set up this service, but if you're using Windows, make sure to run these commands beforehand:

    ```bash
   git config --global core.autocrlf input
   git config --global core.eol lf
   ```

   Then, start by cloning the repository to your local machine. Note, that the recommended way of doing it is using SSH. Check [this link](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/adding-a-new-ssh-key-to-your-github-account) for more information.

   ```bash
   git clone git@github.com:kukuruzvelt/functional-stability-analyzer.git
   cd functional-stability-analyzer
   ```

2. **Start the project**

   Use the make command to start the project. It will up the container, install dependencies, and run migrations to the DB.

   ```bash
   make start
   ```

   **It will be better to wait a few minutes after this command executes, before moving further. You can run `make logs` to check the state of service**

   That's it! You can go to `http://localhost:3000/` to access the interface.


3. **Testing**

    To launch e2e tests, use `make e2e-tests` command.


4. **Stopping the Application**

   To stop the application and shut down the containers, run the following command `make down`.
