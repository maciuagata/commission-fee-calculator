<?php

namespace Payment\CommissionTask;

use Payment\CommissionTask\CsvReader;

class CalculateCommissionFee {
  
    //declare variables
    private $commissionFeeCashIn = 0.03;
    private $maxCommissionFeeInEuroCashIn = 5;
    private $commissionFeeCashOut = 0.3;
    private $minCommissionFeeInEuroCashOut = 0.5;
    private $currencyUSD = 1.1497;
    private $currencyJPY = 129.53;
    private $discountRules = 3;
    private $freeOfChargeAmount = 1000;
    private $commissionFeesResult= [];
    
   
    public function inputDataInFile($file) {
        $input = new CsvReader;
        $inputData = $input->readCsv($file);
        $this->calculateData($inputData);
        $this->showResults();
        $input->writeCsv('result.csv', $this->commissionFeesResult);
    }

    public function calculateData($inputData) {
        foreach($inputData as $value) {
            if("natural" == $value[2]) {
                $this->manageNaturalPerson($value, $inputData);
            } else if ("legal" == $value[2]) {
                $this->manageLegalPerson($value);
            } else {
                die('User type is not correct, user ID: ' . $value[1]);
            }
        }
    }
    
    // User identify
    public function manageNaturalPerson($userData, $allUsersData) {
        if("cash_in" == $userData[3]) {
            $this->manageCashIn($userData);
        } else if ("cash_out" == $userData[3]) {
            $this->manageCashOutNaturalPerson($userData, $allUsersData);
        } else {
            die('Transaction type is not described, user ID: ' . $value[1]);
        }
    }

    public function manageLegalPerson($userData) {
        if("cash_in" == $userData[3]) {
            $this->manageCashIn($userData);
        } else if ("cash_out" == $userData[3]) {
            $this->manageCashOutLegal($userData);
        } else {
            die('Transaction type is not described, user ID: ' . $value[1]);
        }
    }

    // Manage cash in operation, cash in is the same for both user types
    public function manageCashIn($userData) {
        
        $calculatedCommissionFee = ($userData[4] * $this->commissionFeeCashIn) / 100;

        // convert maxCommissionFee -> 5Eur to descriped currencies (if it's Eur, method return the same value -> 5Eur)
        $maxCommissionFee = $this->currencyConverter($this->maxCommissionFeeInEuroCashIn, $userData[5]);
        
        // Checking if calculatedCommissionFee is not more than maxCommissionFee
        if($calculatedCommissionFee < $maxCommissionFee) {
            // if not more, it's rounded to smallest currency item to upper bound (for example: 0.023Eur to 0.03Eur)
            $result = $calculatedCommissionFee;
            $result = number_format(ceil($result * 100) / 100, 2, '.', '');
        } else {
            // if more, result commission fee will be 5.00Eur
            $result = number_format(5, 2, '.', '');
        }
        $this->commissionFeesResult[] = $result;
    }

    // Manage cash out with natural user
    public function manageCashOutNaturalPerson($userData, $allUsersData) {
        // Create an array and put in that array only current users procedures
        $userProcedures = [];
        foreach($allUsersData as $user) {
            if($userData[1] == $user[1] && 'natural' == $user[2] && 'cash_out' == $user[3]) {
                $userProcedures[] = $user;
            }
        }

        // remove encoding symbols from $users dates,to get timestamp and use strtotime 
        //strtotime - parse about any English textual datetime description into a Unix timestamp
        $currentDate = $this->removeEncodingSymbols($userData[0]);
        // Get timestamp 1 week before current procedure date
        $timestampBeforeOneWeek = strtotime("$currentDate -1 Week");
        // Current procedure date timestamp
        $currentDateTimestamp = strtotime($currentDate);

        $countTimes = 0;

        // Get all user procedures dates timestamps and if this timestamp are between current procedure 
        // date and current procedure date before 1 week, count times
        foreach($userProcedures as $procedure) {
            $procedureTimestamp = strtotime($this->removeEncodingSymbols($procedure[0]));
            if ($procedureTimestamp < $currentDateTimestamp && $procedureTimestamp > $timestampBeforeOneWeek) {
                $countTimes++;
            }     
        }

        // If counttimes is less than discountRules(in this case 3 times), the user didn't exceed 
        // 3 cashout procedure per week rule, so he will get discount
        if($this->discountRules > $countTimes) {
            // convert free of charge amount(1000Eur) to described currency
            $freeOfCharge = $this->currencyConverter($this->freeOfChargeAmount, $userData[5]);

            // If cash out procedure amount is not more than $freeOfCharge(1000Eur) commission fee is 0,00Eur
            if($userData[4] <= $freeOfCharge) {
                $result = number_format(0, 2, '.', '');
                
                $this->commissionFeesResult[] = $result;
            } else {
                // if cash out procedure amount is bigger than $freeOfCharge(1000Eur),
                // commission is calculated only from exceeded amount
                $amountToFee = $userData[4] - $freeOfCharge;

                // calculate commission fee
                $calculatedCommissionFee = ($amountToFee * $this->commissionFeeCashOut) / 100;
                $result = number_format(ceil($calculatedCommissionFee * 100) / 100, 2, '.', '');
                $this->commissionFeesResult[] = $result;
            }

        } else {
            // If counttimes is more than discountRules(in this case 3 times)
            // Calculating fees from all amount
            $amountToFee = $userData[4];
            $calculatedCommissionFee = ($amountToFee * $this->commissionFeeCashOut) / 100;
            $result = number_format(ceil($calculatedCommissionFee * 100) / 100, 2, '.', '');
            $this->commissionFeesResult[] = $result;
        }
    }

    // Manage cash out with legal user type
    public function manageCashOutLegal($userData) {
        
        $calculatedCommissionFee = ($userData[4] * $this->commissionFeeCashOut) / 100;

        // convert minCommissionFee -> 0.5Eur to descriped currencies (if it's Eur, method return the same value -> 0.5Eur)
        $minCommissionFee = $this->currencyConverter($this->minCommissionFeeInEuroCashOut, $userData[5]);

        // Checking if calculatedCommissionFee is more than minCommissionFee
        if($calculatedCommissionFee > $minCommissionFee) {
            // if more, it's rounded to smallest currency item to upper bound (for example: 0.523Eur to 0.53Eur)
            //ceil - round numbers
            $result = $calculatedCommissionFee;
            $result = number_format(ceil($result * 100) / 100, 2, '.', '');
        } else {
             // if not more, result of commission fee will be 0.50Eur
            $result = number_format($minCommissionFee, 2, '.', '');
        }
        $this->commissionFeesResult[] = $result;
    }

    // Method converting money to currencies in input.csv file
    public function currencyConverter($money, $currency) {
        // trim is strip whitespace from the beginning and end of a string
        $currency = trim($currency);
        if('USD' == $currency) {
            return $money * $this->currencyUSD;
        } else if ('JPY' == $currency) {
            return $money * $this->currencyJPY;
        } else {
            return $money;
        }
    }
    //  get timestamp and remove encoding symbols
    public function removeEncodingSymbols($date) {
        //Remove encoding symbols from string begin
        //pack - pack data into binary string
        // substr - return part of a string
        if(substr($date,0,3) == pack("CCC",0xef,0xbb,0xbf)) {
            $date = substr($date, 3);
        };
        return $date;
    }

    public function showResults() {
        foreach($this->commissionFeesResult as $value) {
            echo $value;
            echo "\r\n";
        }
    }
}