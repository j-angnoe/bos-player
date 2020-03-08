<?php

require __DIR__ . '/../includes.php';

try {
    /**
     * We are going to test if bos-exec can write a environment file.
     * That means that we supply it an environment file that 
     * does not exist yet.
     */

    $TMP_DIR = TMP_DIR;

    if (file_exists("$TMP_DIR/new-environment.json")) {
         unlink("$TMP_DIR/new-environment.json");
    }
    @mkdir('/tmp/bos-unit-test-userdata');

    $commandPrefix = "bos-exec --catalogue $TMP_DIR --environment $TMP_DIR/new-environment.json --data /tmp/bos-unit-test-userdata/ ";

    $myJsonString = escapeshellarg(json_encode(['test'=>1]));

    assertString(
         // I'm catting the json to bos-exec here.
         `echo $myJsonString | $commandPrefix environment:write`,
         // It should say this:
         'Written environment file'
    );

    if (!file_exists("$TMP_DIR/new-environment.json")) {
        fail("new-environment.json should be written to $TMP_DIR");
    }

} catch (Exception $e) {
    fatalException($e);
}