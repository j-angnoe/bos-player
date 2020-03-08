<?php

namespace BOS\Player\Exceptions;

use Exception;

/**
 * This exception is triggered when one of the module executors
 * try to require a non-php file.
 */
class FileExecutionNotAllowed extends Exception {}
    