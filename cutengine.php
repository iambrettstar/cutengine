<?php

/**
 * Very basic Qt unit test engine wrapper for arcanist / phabricator
 */ 
final class QtEngine extends ArcanistUnitTestEngine {
    
    public function run() {
        # get OS
        exec("uname", $os);

        /*
        This doesn't cater for windows - but then we don't dev in windows, we 
        just create installers there...
        */
        $command_key = $os[0] == "Darwin" ? 'unit.engine.command.macx' : 'unit.engine.command.unix';

        /*
        Use some arcanist magic to get the command to run, stored in the project .arcconfig file.
        These should be something like "project_name -xunitxml", assuming different directories
        for mac and unix
        */
        $builddir = $this->getConfigurationManager()->getConfigFromAnySource('unit.engine.builddir');
        $command = $builddir . $this->getConfigurationManager()->getConfigFromAnySource($command_key);

        $results = shell_exec($command);

        # the xml that is returned has this pesky leading line...let's take that out
        $results = preg_replace('/(<[\?]xml version=\"1.0\" encoding=\"UTF-8\" [\?]>)/', '', $results);
        $xml_results = "<qtunit>" . $results . "</qtunit>";

        $xml = simplexml_load_string($xml_results);
        $json = json_encode($xml);

        return $this->parseJsonOutput($json, $builddir);
    }

    private function parseJsonOutput($json, $builddir) {
        $json_array = json_decode($json, true);

        $results = array();
        $coverage = $this->readCoverage($builddir);
    
        foreach($json_array["testsuite"] as $testsuite) {
            # extract the relevant results using xpath
            $suiteName = $testsuite["@attributes"]["name"];

            # TODO: we could use these in future...
            #$testCount = $testsuite["@attributes"]["tests"];
            #$errorCount = $testsuite["@attributes"]["errors"];
            #$failureCount = $testsuite["@attributes"]["failures"];

            foreach($testsuite["testcase"] as $testcase) {
                $result = new ArcanistUnitTestResult();
                $result->setName($suiteName . "::" . $testcase["@attributes"]["name"]);
                switch($testcase["@attributes"]["result"]) {
                    case 'pass':
                        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
                        break;
                    case 'fail':
                        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                        $result->setUserData($testcase["failure"]["@attributes"]["message"]);
                        break;
                    default:
                        $result->setResult(ArcanistUnitTestResult::RESULT_UNSOUND);
                        $result->setUserData($testcase["@attributes"]["result"]);
                }
                $result->setCoverage($coverage);
                $results[] = $result;
            }	
        } 

        return $results;
    }
  
    private function readCoverage($builddir) {
        $reports = array();
        # TODO: build a coverage script for qt unit tests....
        
        return $reports;
    }
}
