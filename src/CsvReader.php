<?php

namespace Payment\CommissionTask;

class CsvReader {
    // read csv file and return array
    public function readCsv($fileName) {
        $procedures = [];
        $file = file($fileName);
        foreach($file as $procedure) {
            $procedures[] = explode(',', $procedure);
        }
        return $procedures;
    }

    // write into csv file given array of commission results
    public function writeCsv($fileName, $procedures) {
        $file = fopen($fileName, 'w');
        foreach($procedures as $procedure) {
            $val = explode(",", $procedure);
            fputcsv($file, $val);
        }
        fclose($file);
    }
}
