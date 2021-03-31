## Purpose

The tests in this directory are intended to test that the behaviour of the stream wrapper 
matches the behaviour of normal PHP file streams.

The individual tests should remain completely independent of the implementation details of 
the wrapper. The only library-specific logic should be in the `BaseFunctionalTestCase` class.

## Testing the Tests

In order to verify that the tests match the behaviour of real PHP file streams, an additional
test mode is included which runs the assertions against a real file created in the system temp
directory.

To run these, point PHPUnit at the config file in this directory:

    vendor/bin/phpunit -c tests/phpunit/functional/testthetests.phpunit.xml

## Adding New Types of Test

In order for the above to work, all the setup and fixture manipulation should be maintained in 
`BaseFunctionalTestCase`. Each method includes two implementations: one using `vfsStream` APIs, 
and one using the real file system. 