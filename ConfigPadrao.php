<?php

/**
 * Fuso horário
 */
if(!ini_get('date.timezone'))
    date_default_timezone_set('America/Sao_Paulo');

/**
 * Número de tentativas e timeout antes de falhar
 */
define('RETRIES',  5);
define('TIMEOUT',  5);

/**
 * Modo de desenvolvimento. Exibe erros e outras informações.
 */
define('DESENVOLVIMENTO', true);

/**
 * Exibe informações de DEBUG quando igual a $_GET['DEBUG']
 */
define('HASH_DEBUG', 'HASH SECRETO PARA DEBUG');