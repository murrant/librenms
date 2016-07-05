# Coding guidelines

This document is here to help code standards for contributions towards LibreNMS. The original code base that we forked from had a lack of standards and as such the code base has a variety of different styles. Whilst we don't want to restrict how people write code, these guidelines should mean we have a good standard going forward that makes reading the code easier. All modern day ide's should be able to assist in these guidelines without breaking your usual workflow.

### Indentation
Please use four (4) spaces to indent code rather than a tab. Ensure you increase indentation for nested code blocks.
```php
if ($foo == 5) {
    if ($foo == 5) {
        if ($foo == 5) {
```

### Line length
Try to keep the length of a line to about 75-85 characters. This isn't essential but does enable compatibility for all screen sizes but above all enables reading of code easier.

### Control structures
A space should be used both before and after the parenthesis and also surrounding the condition operator.
```php
if ($foo == 5) {
```

Rather than

```php
if($foo==5){
```

Don't put blocks of code on a single line as in this example.
```php
if ($foo == 5) { echo 'foo is 5'; }
```

and instead format the code like.
```php
if ($foo == 5) {
    echo 'foo is 5';
}
```

Start else and elsif on new lines, e.g.
```php
if ($foo == 5) {
    echo 'foo is 5';
}
elsif ($foo == 4) {
    echo 'foo is 4';
}
else {
    echo 'foo is something else';
}
```
This makes diffs much cleaner when moving around blocks of code.


### Including files
Using parenthesis around file includes isn't required, instead just place the file in between ''
```php
require_once 'includes/snmp.inc.php';
```

### PHP tags
Ensure you use <?php rather than the shorthand version <?.
```php
<?php
```

The `?>` tag isn't required for included php files. For instance anything in includes/ or html/includes don't need the tag along with config.php. If the php file is standalone then you still need the tag. If you are unsure, add it in :)
