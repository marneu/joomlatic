syslogauthlog
=============

# Joomla! - System - Syslog AuthLog - Plugin

A Syslog authentication logger plugin for Joomla 2.5 and 3, which logs selected authentication events to AUTH log.

## Logged Events
- User login
- User login failure
- User logout
- User logout failure

## Which data is logged?
System syslog sets:
- Date
- Time

Plugin logs:
- Severity
- System user (for virtual environments)
- Event
- Username
- Details (if any)
- User IP

## Features
Configurable options:
- log mode
  - succesful and failed
  - failed only
- log event
  - all events
  - only login
  - only logout
- log environment
  - front- and backend
  - backend only

## Installation
Upload/Install the syslogauthlog plugin package and activate it in the Plug-in Manager. Select your logging options.
The log entries will appear in your systems auth log directory (depending on distribution).

## (unix) syslog

Format: 'Dec 27 13:14:14 host jauthlog[PID]: [SYSUSER] {PRIORITY} {EVENT} {USERNAME} {ADMIN} {MESSAGE} from {CLIENTIP}'

Syslog facility: LOG_AUTH

Syslog priority:
- LOG_INFO ~ Normal log entry
- LOG_WARNING ~ Failure conditions

## Special configuration

Fail2ban can react on error conditions if you configure a block and a filter.

For debian/ubuntu systems in jail.local:
```ini
…
 [joomla-admin]
 enabled  = true
 port     = http,ftp
 filter   = joomla-admin
 logpath  = /var/log/auth.log
 maxretry = 2
 bantime  = 84600
 findtime = 21150
…
```

in filter.d/joomla-admin.conf
```ini
 [Definition]
 failregex =  .* WARNING login admin ADMIN .* from <HOST>$
```
Adopt the failregex to your needs, the text WARNING will usually be set only for failed logins.

---

## History
  - 2022-11-30: Version 1.6: Updated for Joomla 4 (contributed by Helmut Müller)
  - 2017-03-09: Version 1.5: Added quotes to language strings (suggested by prophoto)
  - 2016-11-15: Version 1.4: Added it-IT translation (contributed by Marco Petrini)
  - 2016-02-01: Version 1.3: Fix error logging 
  - Version 1.2: Typos corrected
  - Version 1.1: Fix $syslog, $version usage context
  - Version 1.0.8: Fix JLoggerSyslog usage in Joomla Versions 2
