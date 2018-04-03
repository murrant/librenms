source: Extensions/Poller-Service.md
# Poller Service

The new poller service (`librenms-service.py`) replaces the old poller service (`poller-service.py`), improving its reliability. It's mostly compatible with the old service, but testing is recommended before switching over.

If you are currently using the old poller service, it's strongly recommended that you migrate away - it has a serious defect under certain versions of mysql/mariadb, and may be inadvertently DoS'ing your devices. The new service does not have this issue,

Make sure you uninstall the old poller service before deploying the new one.

## External Requirements
#### A recent version of Python
The poller service will work under Python 2.7+, but some features require behaviour only found in Python3.4+.

If you can't use Python 3.4 or higher, you'll need to manually restart the service whenever LibreNMS is updated.

#### A python mysql driver
PyMySQL is recommended as it requires no C compiler to install. MySQLclient can also be used, but does require compilation.

This can be obtained from your OS package manager, or from PyPI.

#### A python redis driver (distributed polling only)
If you are polling in a distributed deployment, redis-py is required.

#### Redis (distributed polling only)
If you want to use distributed polling, you'll need a redis instance to coordinate the nodes. It's recommeded that you do not share the redis database with any other system - by default, redis supports up to 16 databases (numbered 0-15).

It's strongly recommended that you deploy a resilient cluster of redis systems, and use redis-sentinel.

#### MySQL
You should already have this, but the pollers do need access to the SQL database.

## Configuration

There are a number of configuration options; not all of them are required. They can either be entered into `config.php`, or directly into the database.

### Basic Configuration

The defaults are shown here - it's recommended that you at least tune the number of workers.

```php
$config['service_poller_workers']              = 24;     # Processes spawned for polling
$config['service_services_workers']            = 8;      # Processes spawned for service polling
$config['service_discovery_workers']           = 16;     # Processes spawned for discovery


//Optional Settings
$config['service_poller_frequency']            = 300;    # Seconds between polling attempts       
$config['service_services_frequency']          = 300;    # Seconds between service polling attempts
$config['service_discovery_frequency']         = 21600;  # Seconds between discovery runs 
$config['service_billing_frequency']           = 300;    # Seconds between billing calculations
$config['service_billing_calculate_frequency'] = 60;     # Billing interval 
$config['service_poller_down_retry']           = 60;     # Seconds between failed polling attempts
$config['service_loglevel']                    = 'INFO'; # Must be one of 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'
$config['service_update_frequency']            = 86400;  # Seconds between LibreNMS update checks 
```

There are also some SQL options, but these should be inherited from your LibreNMS web UI configuration.

Logs are sent to the system logging service (usually `journald` or `rsyslog`) - see https://docs.python.org/3/library/logging.html#logging-levels for the options available.

### Distributed Polling Configuration

Once you have your redis database set up, configure it as follows.

```php
$config['redis_host']                          = '127.0.0.1';  # IP or hostname of your redis (or redis sentinel) instance 
$config['redis_db']                            = 0;            # Database number (see requirements)
$config['redis_pass']                          = null;         # Redis auth password
$config['redis_port']                          = 6379;         # Port listening
$config['redis_socket']                        = null;         # UNIX domain socket path (conflicts with host and port options)
```

You should not rely on the password for the security of your system. See https://redis.io/topics/security

```php
distributed_poller                             = true;  # Set to true to enable distributed polling
distributed_poller_name                        = null;  # Uniquely identifies the poller instance
distributed_poller_group                       = 0;     # Which group to poll
```

## Cron Scripts
Once the poller service is installed, the cron scripts used by LibreNMS are no longer required and must be removed.

## Service Installation
A systemd unit file is provided - the sysv and upstart init scripts could also be used with a little modification.

### systemd
A systemd unit file can be found in `misc/librenms.service`. To install run `cp /opt/librenms/misc/librenms.service /etc/systemd/system/librenms.service && systemctl enable --now librenms.service`

## OS-Specific Instructions

### RHEL/CentOS
To get the poller service running under python3.4+ on RHEL-derivatives with minimal fuss, you can use the software collections build:

First, enable SCL's on your system:

#### CentOS 7
```
# yum install centos-release-scl
```

#### RHEL 7
```
# subscription-manager repos --enable rhel-server-rhscl-7-rpms
```

Then install and configure the runtime and service:

```
# yum install rh-python36 epel-release
# yum install redis
# vi /opt/librenms/config.php
# vi /etc/redis.conf
# systemctl enable --now redis.service
# scl enable rh-python36 bash
# pip install pymysql redis
# cp /opt/librenms/misc/librenms.service.scl /etc/systemd/system/librenms.service
# systemctl enable --now librenms.service
```

If you want to use another version of python 3, change `rh-python36` in the unit file and the commands above to match the name of the replacement scl.
