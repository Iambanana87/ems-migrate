<?php
$_GET['action'] = 'count_actions';
$_GET['token'] = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIwIiwibmFtZSI6IlBhcml0eVNjYW4iLCJ1c2VybmFtZSI6InBhcml0eV9zY2FuIiwicm9sZSI6ImFkbWluIiwiZXhwIjoxODA0MjMyMDA4LCJpYXQiOjE3NDE0MTYwNDJ9.v1v_fC-9uU6-T2DShgq1bLFXDGx38NHYuw11Hlz5h-8';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/api.php?action=count_actions';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'api.php';
