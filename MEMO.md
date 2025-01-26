# Mailcow Dockerized Guidelines

## Introduction
Welcome to the Mailcow Dockerized project! This document provides guidelines to help you contribute effectively.

## Getting Started
3. **Install Rclone**:
    ```sh
    curl https://rclone.org/install.sh | sudo bash
    ```
    Verify the installation:
    ```sh
    rclone --version
    ```
    rclone config
    ```
    ssh -L 53682:127.0.0.1:53682 -p 32798 rn@198.23.246.224
    ```
    open the following link in browse
    http://127.0.0.1:53682/auth?state=wUg4_Ga_jIvwm2zrfdu3gw
    ```
    crontab -e
    0 3 * * * /opt/docker/mailcow-dockerized/backup_to_rclone.sh >> /opt/docker/mailcow-dockerized/backup_cron.log 2>&1
1. **Clone the Repository**: 
    ```sh
    git clone https://github.com/taoziyoyo2566/mailcow-dockerized.git
    cd mailcow-dockerized
    ```

2. **Install Dependencies**:
    Follow the instructions in the [installation guide](https://mailcow.github.io/mailcow-dockerized-docs/).

## Contribution Guidelines
1. **Fork the Repository**: Create a personal fork of the repository on GitHub.
2. **Create a Branch**: 
    ```sh
    git switch specify-ip
    ```
3. **Make Changes**: Implement your changes in the new branch.
4. **Commit Changes**: 
    ```sh
    git commit -m "Description of your changes"
    ```
5. **Push Changes**: 
    ```sh
    git push origin feature/your-feature-name
    ```
6. **Create a Pull Request**: Submit a pull request to the main repository.

## Code of Conduct
Please adhere to the [Code of Conduct](https://mailcow.github.io/mailcow-dockerized-docs/code_of_conduct/) to maintain a welcoming community.

## Support
For any questions or support, please refer to the [support page](https://mailcow.github.io/mailcow-dockerized-docs/support/).

Thank you for contributing to Mailcow Dockerized!
