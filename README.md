# SuperDemo

[![CI](https://github.com/DenZ1971/symfony-document-workflow/actions/workflows/ci.yml/badge.svg)](https://github.com/DenZ1971/symfony-document-workflow/actions/workflows/ci.yml)


## About the project


SuperDemo is a demonstration Symfony 6.4 project inspired by document management workflows used in modern business applications.


The project implements a document life cycle using Symfony Workflow (State Machine), REST API and PHPUnit tests.


Main features:


* Customer management;
* Document creation;
* Document state management using Symfony Workflow;
* REST API;
* Docker-based development environment;
* Functional and unit testing with PHPUnit.


---


## Technologies


* PHP 8.2
* Symfony 6.4 (LTS)
* Doctrine ORM
* Doctrine Migrations
* MySQL 8
* Symfony Workflow (State Machine)
* PHPUnit 11
* Docker & Docker Compose
* REST API


---


## Document workflow


A document can have only one state at a time.


```
                 validate
draft ----------------------------> validated
  |                                     |
  |                                     |
