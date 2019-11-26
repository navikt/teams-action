<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

class ResultPrinter {
    /**
     * Print results
     *
     * @param array $results List of TeamResult instances
     * @return void
     */
    public function print(array $results) : void {
        $output = [];

        foreach ($results as $teamResult) {
            if ($teamResult->failed()) {
                $output[] = sprintf('%s: (error) %s', $teamResult->getTeamName(), $teamResult->getMessage());
            } else if ($teamResult->skipped()) {
                $output[] = sprintf('%s: (skipped) %s', $teamResult->getTeamName(), $teamResult->getMessage());
            } else {
                $output[] = sprintf('%s: Created', $teamResult->getTeamName());
            }
        }

        echo implode(PHP_EOL, $output);
    }
}