<?php

// JKF 3 July 2019 ZendTo
// Very simple autoloader for anything in lib/* whose filename is the same
// as the class name with the directory separatories fixes ( \ ==> / ).

spl_autoload_register(function ($class) {
  $filename = __DIR__.'/'.str_replace("\\", "/", $class).'.php';
  #print "Maybe going to include $filename\n";
  if (is_readable($filename)) {
    #print "Definitely going to include $filename\n";
    include $filename;
  }
});

?>
