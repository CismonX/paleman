# Introduction

## Summary
Paleman is a PHP framework based on Workerman, the latter is a open-source class library mainly for PHP socket programming. Paleman provides a convenient for developers to create RPC-like applications, which can be timed, distributed, and with many other features.

## Structure of Paleman
A Paleman instance consists of a number of processes called workers, which are mainly devided into three types:
* HTTP Workers listen to HTTP requests from control panel, parse the request, and give commands to Task Workers.
* Task Workers execute the tasks and send messages to WebSocket Worker. They add new tasks, adjust or stop existing tasks according to the commands given by HTTP Workers.
* WebSocket Workers recieve messages from Task Workers, and send them to the specified clients.

Note that Channel servers and GlobalData servers are also workers, and they are used as agents for communication between processes.

## How to start
Paleman can only run in php-cli on Unix-like platforms. Make sure all files are succesfully included and all parameters are valid (check Paleman\config.php), and run "php start.php start" (or "php start.php start -d" to start as daemon) as bash command. In some cases, you should run PHP as superuser.

## Distributed deployment
Workers use socket to communicate with each other. Thus it's convenient for distributed deployment. Just modify a bit of code, and it will be okay. I don't provide the solution here.

# Development

## Mechanics
There are three types of operations shown below, which can be sent to Paleman via control panel. 
* add: Initiates a new instance of an application with the parameters given.
* set: Do something with the parameters given, but do not create new instances.
* del: Destruct the specific instance, and free the space it occupies.

Note:
* Each Task worker can execute 0 to many instances simutanously, but it's single threaded. So if one of them is blocked, all instances on that worker is also blocked. It's recommended that you put a heavy task on a single worker. Pthread extension can also do, but it's deprecated for the fact that it has bugs which may cause unpredictable exceptions.
* Parameters should be given as a string, use space character to indent the parameters.

## Add functions

Each application has an add function to create a new instance, or we can say, a new task.

Function name should be "$app\_name"+"\_add", such as "GithubSearch\_add". Add operation requires at lease two parameters, "add $app\_name $para\_3 $para\_4 ... $para\_n\>". The first argument "add" indicates the operation, the second specifies the application, all other arguments will be passed to the add function as one parameter (array).

Add function should have only one argument. Return value should be a key-and-value array. The format of the array is given below.
* "interval" => (int) : Time interval (seconds) before the timer function is called again. If not set, timer function will never be called.
* "return" => (array) : Keys of the array members, which will be sent back to control panel.
* "args" => (array) : Keys of the array members, which will be passed to initialize function and timer function as parameters.
* "init" => (callable) : Initialize function, which will be further introduced in this document.
* "timer" => (callable) : Timer function, which will be further introduced in this document.
* "worker_id" => (int) : Specify task worker ID. If not set, Paleman will choose random worker.
* "err" => (string) : Error messages. Can be set only when an error occurs and you want to abort the task. Initialize function and timer function will not be called, and error message will be sent to control panel.
* Other members.

Note:
* Add function is called in HTTP worker. Do not put tasks here.
* "$task_id" will be automatically added to the return value of add funtions.

## Initialize functions
After an instance is successfully created, initialize function will be called.

Initialize function should have two arguments, the first is an array from add function, the second is a string, "$task\_id". It's a unique ID generated by system, and specifies the current task.

Return value can be of any type. If return true, then the task continues normally. If return array and key "err" is set, then the task will be aborted and error message will be sent to stdout. In other occasions, return value will be added to the array with key "init", and pass to the timer function as parameter.

## Timer functions
Timer function will be called repeatedly after initialization according to the function and interval given.

Initialize function should have two arguments, the first is an array from add function and initialization function, the second is "$task\_id".

Timer retrieves timer function from GlobalData every time it executes, thus it's possible to change timer function. if GlobalData "timer_func" is set to false, nothing will happen.

Return value can be of any type. If not return true, it will be sent to client.

Note that both timer functions and initialize functions are called in the same Task worker.

## Configure functions

Configure functions are independent from tasks, and they are called in HTTP workers when the control panel initiates a set operation. Normally, configure functions are used to modify the settings of current running tasks.

Function name should be "$func\_name"+"\_set", such as "user\_set". Parameter passing of configure functions is identical to that of add functions.

----to be continued