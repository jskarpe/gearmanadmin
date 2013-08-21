<?php
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind ($socket, 'localhost', 11111);
socket_getsockname($socket, $socketAddress, $socketPort);
socket_listen($socket);

socket_accept($socket);