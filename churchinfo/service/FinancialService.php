<?php
require_once "PersonService.php";
require_once "FamilyService.php";
require_once dirname(dirname(__FILE__))."/Include/Functions.php";
require dirname(dirname(__FILE__))."/Include/MICRFunctions.php";

class FinancialService {
    
    private $baseURL;
    private $personService;
    private $familyService;

    public function __construct() 
    {
        $this->baseURL = $_SESSION['sURLPath'];
        $this->personService = new PersonService();
        $this->familyService = new FamilyService();
    }
	
    function processAuthorizeNet()
    {
        $donation = new AuthorizeNetAIM;
                $donation->amount = "$plg_amount";
                $donation->first_name = $firstName;
                $donation->last_name = $lastName;
                $donation->address = $address1 . $address2;
                $donation->city = $city;
                $donation->state = $state;
                $donation->zip = $zip;
                $donation->country = $country;
                $donation->description = "UU Nashua Pledge";
                $donation->email = $email;
                $donation->phone = $phone;
        
                // not setting these
        //		$donation->allow_partial_auth
        //		$donation->auth_code
        //		$donation->authentication_indicator
        //		$donation->bank_aba_code
        //		$donation->bank_check_number
        //		$donation->card_code
        //		$donation->cardholder_authentication_value
        //		$donation->company
        //		$donation->cust_id
        //		$donation->customer_ip
        //		$donation->delim_char
        //		$donation->delim_data
        //		$donation->duplicate_window
        //		$donation->duty
        //		$donation->echeck_type
        //		$donation->email_customer
        //		$donation->encap_char
        //		$donation->fax
        //		$donation->footer_email_receipt
        //		$donation->freight
        //		$donation->header_email_receipt
        //		$donation->invoice_num
        //		$donation->line_item
        //		$donation->login
        //		$donation->method
        //		$donation->po_num
        //		$donation->recurring_billing
        //		$donation->relay_response
        //		$donation->ship_to_address
        //		$donation->ship_to_city
        //		$donation->ship_to_company
        //		$donation->ship_to_country
        //		$donation->ship_to_first_name
        //		$donation->ship_to_last_name
        //		$donation->ship_to_state
        //		$donation->ship_to_zip
        //		$donation->split_tender_id
        //		$donation->tax
        //		$donation->tax_exempt
        //		$donation->test_request
        //		$donation->tran_key
        //		$donation->trans_id
        //		$donation->type
        //		$donation->version
            
                if ($dep_Type == "CreditCard") {
                    $donation->card_num = $creditCard;
                    $donation->exp_date = $expMonth . "/" . $expYear;
                } else {
                    // check payment info if supplied...
                    
        // Use eCheck:
                    $donation->bank_acct_name = $firstName . ' ' . $lastName;
                    $donation->bank_acct_num = $account;
                    $donation->bank_acct_type = 'CHECKING';
                    $donation->bank_name = $bankName;
                    
                    $donation->setECheck(
                        $route,
                        $account,
                        'CHECKING',
                        $bankName,
                        $firstName . ' ' . $lastName,
                        'WEB'
                    );
                }
        
                $response = $donation->authorizeAndCapture();
                if ($response->approved) {
                    $transaction_id = $response->transaction_id;
                }
                
                if ($response->approved) {
                    // Push the authorized transaction date forward by the interval
                    $sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('" . $authDate . "', INTERVAL " . $aut_Interval . " MONTH) WHERE aut_ID = " . $aut_ID . " AND aut_Amount = " . $plg_amount;
                    RunQuery ($sSQL);
                    // Update the serial number in any case, even if this is not the scheduled payment
                    $sSQL = "UPDATE autopayment_aut SET aut_Serial=aut_Serial+1 WHERE aut_ID = " . $aut_ID;
                    RunQuery ($sSQL);
                }
        
                if (! ($response->approved))
                    $response->approved = 0;
                    
                $sSQL = "UPDATE pledge_plg SET plg_aut_Cleared=" . $response->approved . " WHERE plg_plgID=" . $plg_plgID;
                RunQuery($sSQL);
        
                if ($plg_aut_ResultID) {
                    // Already have a result record, update it.
                    $sSQL = "UPDATE result_res SET " .
                                    "res_echotype1	='" . $response->response_reason_code	. "'," .
                                    "res_echotype2	='" . $response->response_reason_text	. "'," .
                                    "res_echotype3	='" . $response->response_code	. "'," .
                                    "res_authorization	='" . $response->response_subcode	. "'," .
                                    "res_order_number	='" . $response->authorization_code	. "'," .
                                    "res_reference	='" . $response->avs_response	. "'," .
                                    "res_status	='" . $response->transaction_id	. "'" .
                                " WHERE res_ID=" . $plg_aut_ResultID;
                    RunQuery($sSQL);
                } else {
                    // Need to make a new result record
                    $sSQL = "INSERT INTO result_res (
                                    res_echotype1,
                                    res_echotype2,
                                    res_echotype3,
                                    res_authorization,
                                    res_order_number,
                                    res_reference,
                                    res_status)
                                VALUES (" .
                                    "'" . mysql_real_escape_string($response->response_reason_code) . "'," .
                                    "'" . mysql_real_escape_string($response->response_reason_text) . "'," .
                                    "'" . mysql_real_escape_string($response->response_code) . "'," .
                                    "'" . mysql_real_escape_string($response->response_subcode) . "'," .
                                    "'" . mysql_real_escape_string($response->authorization_code) . "'," .
                                    "'" . mysql_real_escape_string($response->avs_response) . "'," .
                                    "'" . mysql_real_escape_string($response->transaction_id) . "')";
                    RunQuery($sSQL);
        
                    // Now get the ID for the newly created record
                    $sSQL = "SELECT MAX(res_ID) AS iResID FROM result_res";
                    $rsLastEntry = RunQuery($sSQL);
                    extract(mysql_fetch_array($rsLastEntry));
                    $plg_aut_ResultID = $iResID;
        
                    // Poke the ID of the new result record back into this pledge (payment) record
                    $sSQL = "UPDATE pledge_plg SET plg_aut_ResultID=" . $plg_aut_ResultID . " WHERE plg_plgID=" . $plg_plgID;
                    RunQuery($sSQL);
                }
    }
    
    function processVanco()
    {
        $customerid = "$aut_ID";  // This is an optional value that can be used to indicate a unique customer ID that is used in your system
                // put aut_ID into the $customerid field
                // Create object to preform API calls
                
                $workingobj = new VancoTools($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);
                // Call Login API to receive a session ID to be used in future API calls
                $sessionid = $workingobj->vancoLoginRequest();
                // Create content to be passed in the nvpvar variable for a TransparentRedirect API call
                $nvpvarcontent = $workingobj->vancoEFTTransparentRedirectNVPGenerator($VancoUrltoredirect,$customerid,"","NO");

                $paymentmethodref = "";
                if ($dep_Type == "CreditCard") {
                    $paymentmethodref = $creditcardvanco;
                } else {
                    $paymentmethodref = $accountvanco;
                }

                $addRet = $workingobj->vancoEFTAddCompleteTransactionRequest(
                    $sessionid, // $sessionid
                    $paymentmethodref,// $paymentmethodref
                    '0000-00-00',// $startdate
                    'O',// $frequencycode
                    $customerid,// $customerid
                    "",// $customerref
                    $firstName . " " . $lastName,// $name
                    $address1,// $address1
                    $address2,// $address2
                    $city,// $city
                    $state,// $state
                    $zip,// $czip
                    $phone,// $phone
                    "No",// $isdebitcardonly
                    "",// $enddate
                    "",// $transactiontypecode
                    "",// $funddict
                    $plg_amount);// $amount

                $retArr = array();
                parse_str($addRet, $retArr);
                
                $errListStr = "";
                if (array_key_exists ("errorlist", $retArr))
                    $errListStr = $retArr["errorlist"];
                
                $bApproved = false;
                
                // transactionref=None&paymentmethodref=16610755&customerref=None&requestid=201411222041237455&errorlist=167
                if ($retArr["transactionref"]!="None" && $errListStr == "")
                    $bApproved = true;
                    
                $errStr = "";
                if ($errListStr != "") {
                    $errList = explode (",", $errListStr);
                    foreach ($errList as $oneErr) {
                        $errStr .= $workingobj->errorString ($oneErr . "<br>\n");
                    }
                }
                if ($errStr == "")
                    $errStr = "Success: Transaction reference number " . $retArr["transactionref"] . "<br>";
                
                    
                if ($bApproved) {
                    // Push the authorized transaction date forward by the interval
                    $sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('" . $authDate . "', INTERVAL " . $aut_Interval . " MONTH) WHERE aut_ID = " . $aut_ID . " AND aut_Amount = " . $plg_amount;
                    RunQuery ($sSQL);
                    // Update the serial number in any case, even if this is not the scheduled payment
                    $sSQL = "UPDATE autopayment_aut SET aut_Serial=aut_Serial+1 WHERE aut_ID = " . $aut_ID;
                    RunQuery ($sSQL);
                }
                    
                $sSQL = "UPDATE pledge_plg SET plg_aut_Cleared='" . $bApproved . "' WHERE plg_plgID=" . $plg_plgID;
                RunQuery($sSQL);
                
                if ($plg_aut_ResultID) {
                    // Already have a result record, update it.
                    
                    $sSQL = "UPDATE result_res SET res_echotype2='" . mysql_real_escape_string($errStr)	. "' WHERE res_ID=" . $plg_aut_ResultID;
                    RunQuery($sSQL);
                } else {
                    // Need to make a new result record
                    $sSQL = "INSERT INTO result_res (res_echotype2) VALUES ('" . mysql_real_escape_string($errStr) . "')";
                    RunQuery($sSQL);
        
                    // Now get the ID for the newly created record
                    $sSQL = "SELECT MAX(res_ID) AS iResID FROM result_res";
                    $rsLastEntry = RunQuery($sSQL);
                    extract(mysql_fetch_array($rsLastEntry));
                    $plg_aut_ResultID = $iResID;
        
                    // Poke the ID of the new result record back into this pledge (payment) record
                    $sSQL = "UPDATE pledge_plg SET plg_aut_ResultID=" . $plg_aut_ResultID . " WHERE plg_plgID=" . $plg_plgID;
                    RunQuery($sSQL);
                }
        
    }
    
    function runTransactions($depID)
    {
        // Process all the transactions

        //Get the payments for this deposit slip
        $sSQL = "SELECT plg_plgID,
                       plg_amount, 
                        plg_scanString,
                             plg_aut_Cleared,
                             plg_aut_ResultID,
                             a.aut_FirstName AS firstName,
                             a.aut_LastName AS lastName,
                             a.aut_Address1 AS address1,
                             a.aut_Address2 AS address2,
                             a.aut_City AS city,
                             a.aut_State AS state,
                             a.aut_Zip AS zip,
                             a.aut_Country AS country,
                             a.aut_Phone AS phone,
                             a.aut_Email AS email,
                             a.aut_CreditCard AS creditCard,
                             a.aut_CreditCardVanco AS creditcardvanco,
                             a.aut_ExpMonth AS expMonth,
                             a.aut_ExpYear AS expYear,
                             a.aut_BankName AS bankName,
                             a.aut_Route AS route,
                             a.aut_Account AS account,
                             a.aut_AccountVanco AS accountvanco,
                             a.aut_Serial AS serial,
                             a.aut_NextPayDate AS authDate,
                             a.aut_Interval AS aut_Interval,
                             a.aut_ID AS aut_ID
                 FROM pledge_plg
                 LEFT JOIN autopayment_aut a ON plg_aut_ID = a.aut_ID
                 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
                 WHERE plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_date";
        $rsTransactions = RunQuery($sSQL);

        if ($sElectronicTransactionProcessor == "AuthorizeNet") {
            require_once 'vendor/sdk-php-1.8.0/AuthorizeNet.php';
            include ("Include/AuthorizeNetConfig.php"); // Specific account information is in here
        }
            
        if ($sElectronicTransactionProcessor == "Vanco") {
            include "Include/vancowebservices.php";
            include "Include/VancoConfig.php";
        }
        
        while ($aTransaction =mysql_fetch_array($rsTransactions))
        {
            extract($aTransaction);

            if ($plg_aut_Cleared) // If this one already cleared do not submit it again.
                continue;

            if ($sElectronicTransactionProcessor == "AuthorizeNet") 
            {
                $this->processAuthorizeNet();
                
            } else if ($sElectronicTransactionProcessor == "Vanco") 
            {
                $this->processVanco();
            }
        }
    }
    
    function loadAuthorized($depID)
    {
            
        // Create all the payment records that have been authorized

        //Get all the variables from the request object and assign them locally
        $dDate = FilterInput($_POST["Date"]);
        $sComment = FilterInput($_POST["Comment"]);
        if (array_key_exists ("Closed", $_POST))
            $bClosed = FilterInput($_POST["Closed"]);
        else
            $bClosed = false;
        $sDepositType = FilterInput($_POST["DepositType"]);
        if (! $bClosed)
            $bClosed = 0;

        // Create any transactions that are authorized as of today
        if ($dep_Type == "CreditCard") {
            $enableStr = "aut_EnableCreditCard=1";
        } else {
            $enableStr = "aut_EnableBankDraft=1";
        }

        // Get all the families with authorized automatic transactions
        $sSQL = "SELECT * FROM autopayment_aut WHERE " . $enableStr . " AND aut_NextPayDate<='" . date('Y-m-d') . "'";

        $rsAuthorizedPayments = RunQuery($sSQL);

        while ($aAutoPayment =mysql_fetch_array($rsAuthorizedPayments))
        {
            extract($aAutoPayment);
            if ($dep_Type == "CreditCard") {
                $method = "CREDITCARD";
            } else {
                $method = "BANKDRAFT";
            }
            $dateToday = date ("Y-m-d");
            
            $amount = $aut_Amount;
            $FYID = $aut_FYID;
            $interval = $aut_Interval;
            $fund = $aut_Fund;
            $authDate = $aut_NextPayDate;
            $sGroupKey = genGroupKey($aut_ID, $aut_FamID, $fund, $dateToday);
            
            // Check for this automatic payment already loaded into this deposit slip
            $sSQL = "SELECT plg_plgID FROM pledge_plg WHERE plg_depID=" . $dep_ID . " AND plg_aut_ID=" . $aut_ID;
            $rsDupPayment = RunQuery ($sSQL);
            $dupCnt = mysql_num_rows ($rsDupPayment);

            if ($amount > 0.00 && $dupCnt == 0) {
                $sSQL = "INSERT INTO pledge_plg (plg_FamID, 
                                                plg_FYID, 
                                                plg_date, 
                                                plg_amount, 
                                                plg_method, 
                                                plg_DateLastEdited, 
                                                plg_EditedBy, 
                                                plg_PledgeOrPayment, 
                                                plg_fundID, 
                                                plg_depID,
                                                plg_aut_ID,
                                                plg_CheckNo,
                                                plg_GroupKey)
                                    VALUES (" .
                                                $aut_FamID . "," .
                                                $FYID . "," .
                                                "'" . date ("Y-m-d") . "'," .
                                                $amount . "," .
                                                "'" . $method . "'," .
                                                "'" . date ("Y-m-d") . "'," .
                                                $_SESSION['iUserID'] . "," .
                                                "'Payment'," .
                                                $fund . "," .
                                                $dep_ID . "," .
                                                $aut_ID . "," .
                                                $aut_Serial . "," .
                                                "'" . $sGroupKey . "')";
                RunQuery ($sSQL);
            }
        }
    }
    
	function deletePayment($groupKey) 
    {
		$sSQL = "DELETE FROM `pledge_plg` WHERE `plg_GroupKey` = '" . $groupKey . "';";
		RunQuery($sSQL);
	}
	
	function getMemberByScanString($sstrnig)
	{
		global $bUseScannedChecks;
		if ($bUseScannedChecks) 
		{ 
			require "../Include/MICRFunctions.php";
			$micrObj = new MICRReader(); // Instantiate the MICR class
			$routeAndAccount = $micrObj->FindRouteAndAccount($tScanString); // use routing and account number for matching
			if ($routeAndAccount) {
				$sSQL = "SELECT fam_ID, fam_Name FROM family_fam WHERE fam_scanCheck=\"" . $routeAndAccount . "\"";
				$rsFam = RunQuery($sSQL);
				extract(mysql_fetch_array($rsFam));
				$iCheckNo = $micrObj->FindCheckNo ($tScanString);
				echo '{"ScanString": "'.$tScanString.'" , "RouteAndAccount": "'.$routeAndAccount.'" , "CheckNumber": "'.$iCheckNo.'" ,"fam_ID": "'.$fam_ID.'" , "fam_Name": "'.$fam_Name.'"}';
			}
			else
			{
				echo '{"status":"error in locating family"}';
			}
		}
		else
		{
			echo '{"status":"Scanned Checks is disabled"}';
		}	
	}

	function getDepositsByFamilyID($fid)
	{
		$sSQL = "SELECT plg_fundID, plg_amount from pledge_plg where plg_famID=\"" . $familyId . "\" AND plg_PledgeOrPayment=\"Pledge\"";
			if ($fyid != -1)
			{
				$sSQL .= " AND plg_FYID=\"" . $fyid . "\";";
			}
			echo $sSQL;
			$rsPledge = RunQuery($sSQL);
			$totalPledgeAmount = 0;
			while ($row = mysql_fetch_array($rsPledge)) {
				$fundID = $row["plg_fundID"];
				$plgAmount = $row["plg_amount"];
				$fundID2Pledge[$fundID] = $plgAmount;
				$totalPledgeAmount = $totalPledgeAmount + $plgAmount;
			} 
			if ($fundID2Pledge) {
				// division rounding can cause total of calculations to not equal total.  Keep track of running total, and asssign any rounding error to 'default' fund
				$calcTotal = 0;
				$calcOtherFunds = 0;
				foreach ($fundID2Pledge as $fundID => $plgAmount) {
					$calcAmount = round($iTotalAmount * ($plgAmount / $totalPledgeAmount), 2);

					$nAmount[$fundID] = number_format($calcAmount, 2, ".", "");
					if ($fundID <> $defaultFundID) {
						$calcOtherFunds = $calcOtherFunds + $calcAmount;
					}

					$calcTotal += $calcAmount;
				}
				if ($calcTotal <> $iTotalAmount) {
					$nAmount[$defaultFundID] = number_format($iTotalAmount - $calcOtherFunds, 2, ".", "");
				}
			} else {
				$nAmount[$defaultFundID] = number_format($iTotalAmount, 2, ".", "");
			}
		
		
	}

	function sanitize($str)
	{
		return str_replace("'","",$str);
	}
    
    function deleteDeposit($id)
    {
        $DeleteDeposits = "DELETE FROM deposit_dep 
            WHERE dep_ID = ".$id;
        RunQuery($DeleteDeposits);
            
        $DeletePledges = "DELETE FROM pledge_plg 
            WHERE plg_depID = ".$id;
        RunQuery($DeletePledges);
            
        $DeletePledgeDenominations = "DELETE FROM pledge_denominations_pdem 
            WHERE plg_depID = ".$id;    
        RunQuery($DeletePledgeDenominations);
            
        
        
        
    }
    
	function getDeposits($id=null) 
    {

		$sSQL = "SELECT * FROM deposit_dep";
		if ($id)
		{
				$sSQL.=" WHERE dep_ID = ".$id;
		}
		$rsDep = RunQuery($sSQL);
		$return = array();
		while ($aRow = mysql_fetch_array($rsDep))
		{
			extract ($aRow);
            $values= new StdClass();
			$values->dep_ID=$dep_ID;
			$values->dep_Date=$dep_Date;
			$values->dep_Comment=$dep_Comment;
			$values->dep_Closed=$dep_Closed;
			$values->dep_Type=$dep_Type;
            $values->dep_EnteredBy = $dep_EnteredBy;
            $values->totalCash=$this->getDepositTotal($dep_ID,'CASH');
            $values->totalChecks=$this->getDepositTotal($dep_ID,'CHECK');
            $values->dep_Total=$this->getDepositTotal($dep_ID);
            $values->countCash=$this->getDepositCount($dep_ID,'CASH');
            $values->countCheck=$this->getDepositCount($dep_ID,'CHECK');
            $values->countTotal=$this->getDepositCount($dep_ID);
			array_push($return,$values);
		}
		return $return;
	}
        
    function setDeposit($depositType, $depositComment, $depositDate, $iDepositSlipID=null, $depositClosed=false)
    {
        if($iDepositSlipID)
        {
            $sSQL = "UPDATE deposit_dep SET dep_Date = '" . $depositDate . "', dep_Comment = '" . $depositComment . "', dep_EnteredBy = ". $_SESSION['iUserID'] . ", dep_Closed = " . intval($depositClosed) . " WHERE dep_ID = " . $iDepositSlipID . ";";
			$bGetKeyBack = false;
			if ($depositClosed && ($depositType=='CreditCard' || $depositType == 'BankDraft')) {
				// Delete any failed transactions on this deposit slip now that it is closing
				$q = "DELETE FROM pledge_plg WHERE plg_depID = " . $iDepositSlipID . " AND plg_PledgeOrPayment=\"Payment\" AND plg_aut_Cleared=0" ;
				RunQuery($q);
			}
            RunQuery($sSQL);
        }
        else
        {
            $sSQL = "INSERT INTO deposit_dep (dep_Date, dep_Comment, dep_EnteredBy,  dep_Type) 
			VALUES ('" . $depositDate . "','" . $depositComment . "'," . $_SESSION['iUserID'] . ",'" . $depositType . "')";
            RunQuery($sSQL);
            $sSQL = "SELECT MAX(dep_ID) AS iDepositSlipID FROM deposit_dep";
			$rsDepositSlipID = RunQuery($sSQL);
			$iDepositSlipID = mysql_fetch_array($rsDepositSlipID)[0];
        }   	
        $_SESSION['iCurrentDeposit'] = $iDepositSlipID;
        return $this->getDeposits($iDepositSlipID);
    }
    
    function getDepositTotal($id,$type=null)
    {
        $sqlClause = '';
        if ($type)
        {
            $sqlClause = "AND plg_method = '".$type."'";
        }
        // Get deposit total
        $sSQL = "SELECT SUM(plg_amount) AS deposit_total FROM pledge_plg WHERE plg_depID = '$id' AND plg_PledgeOrPayment = 'Payment' ".$sqlClause;
        $rsDepositTotal = RunQuery($sSQL);
        list ($deposit_total) = mysql_fetch_row($rsDepositTotal);
        return $deposit_total;    
    }
    
    function getDepositCount($id,$type=null)
    {
        $sqlClause = '';
        if ($type)
        {
            $sqlClause = "AND plg_method = '".$type."'";
        }
        // Get deposit total
        $sSQL = "SELECT COUNT(plg_amount) AS deposit_count FROM pledge_plg WHERE plg_depID = '$id' AND plg_PledgeOrPayment = 'Payment' ".$sqlClause;
        $rsDepositTotal = RunQuery($sSQL);
        list ($deposit_count) = mysql_fetch_row($rsDepositTotal);
        return $deposit_count;    
    }
    
    function getDepositJSON($deposits)
    {
        if ($deposits)
        {
            return '{"deposits":'.json_encode($deposits).'}';
        }
        else
        {
            return false;
        }
        
    }
    
    function getPaymentJSON($payments)
    {
        if ($payments)
        {
             return '{"payments":'.json_encode($payments).'}';
        }
        else
        {
            return false;
        }
       
    }

	function getPayments($depID) 
    {
		$sSQL = "SELECT * from pledge_plg
            INNER JOIN 
            donationfund_fun 
            ON 
            pledge_plg.plg_fundID = donationfund_fun.fun_ID";
            
		if ($depID)
		{
				$sSQL.=" WHERE plg_depID = ".$depID;
		}
		$rsDep = RunQuery($sSQL);
		$return = array();
		while ($aRow = mysql_fetch_array($rsDep))
		{
			extract ($aRow);
			$values['plg_plgID']=$plg_plgID;
			$values['plg_FamID']=$plg_FamID;
            $values['familyName'] = $this->familyService->getFamilyName($plg_FamID);
			$values['plg_FYID']=$plg_FYID;
            $values['FiscalYear']=MakeFYString($plg_FYID);
			$values['plg_date']=$plg_date;
			$values['plg_amount']=$plg_amount;
			$values['plg_schedule']=$plg_schedule;
			$values['plg_method']=$plg_method;
			$values['plg_comment']=$plg_comment;
			$values['plg_DateLastEdited']=$plg_DateLastEdited;
			$values['plg_EditedBy']=$plg_EditedBy;
			$values['plg_PledgeOrPayment']=$plg_PledgeOrPayment;
			$values['plg_fundID']=$plg_fundID;
            $values['fun_Name'] = $fun_Name;
			$values['plg_depID']=$plg_depID;
			$values['plg_CheckNo']=$plg_CheckNo;
			$values['plg_Problem']=$plg_Problem;
			$values['plg_scanString']=$plg_scanString;
			$values['plg_aut_ID']=$plg_aut_ID;
			$values['plg_aut_Cleared']=$plg_aut_Cleared;
			$values['plg_aut_ResultID']=$plg_aut_ResultID;
			$values['plg_NonDeductible']=$plg_NonDeductible;
			$values['plg_GroupKey']=$plg_GroupKey;

			array_push($return,$values);
		}
		echo '{"pledges": ' . json_encode($return) . '}';
		
	}

    function searchDeposits($searchTerm)
    {
        $fetch = 'SELECT dep_ID, dep_Comment, dep_Date, dep_EnteredBy, dep_Type
            FROM deposit_dep
            LEFT JOIN pledge_plg ON
                pledge_plg.plg_depID = deposit_dep.dep_ID
                AND
                plg_CheckNo LIKE \'%'.$searchTerm.'%\' 
            WHERE  
            dep_Comment LIKE \'%'.$searchTerm.'%\' 
            OR 
            dep_Date LIKE \'%'.$searchTerm.'%\' 
            OR
            plg_CheckNo LIKE \'%'.$searchTerm.'%\'      
            LIMIT 15';    
        $result=mysql_query($fetch);
        $deposits = array();
        while($row=mysql_fetch_array($result)) {
            $row_array['id']=$row['dep_ID'];
            $row_array['displayName']=$row['dep_Comment']." - ".$row['dep_Date'];
            $row_array['uri'] = $this->getViewURI($row['dep_ID']);
            array_push($deposits,$row_array);
        }
        return $deposits;  
        
    }
    
    function searchPayments($searchTerm)
    {
        $fetch = 'SELECT dep_ID, dep_Comment, dep_Date, dep_EnteredBy, dep_Type, plg_FamID, plg_amount, plg_CheckNo, plg_plgID, plg_GroupKey
            FROM deposit_dep
            LEFT JOIN pledge_plg ON
                pledge_plg.plg_depID = deposit_dep.dep_ID
            WHERE 
            plg_CheckNo LIKE \'%'.$searchTerm.'%\' 
            LIMIT 15';
        $result=mysql_query($fetch);
        $deposits = array();
        while($row=mysql_fetch_array($result)) {
            $row_array['id']=$row['dep_ID'];
            $row_array['displayName']="Check #".$row['plg_CheckNo']." ".$row['dep_Comment']." - ".$row['dep_Date'];
            $row_array['uri'] = $this->getPaymentViewURI($row['plg_GroupKey']);
            array_push($deposits,$row_array);
        }
        return $deposits;  
        
    }
    
    function getPaymentViewURI($groupKey)
    {
        return $this->baseURL ."/PledgeEditor.php?GroupKey=".$groupKey;
    }
    
    function getViewURI($Id)
    {
        return $this->baseURL ."/DepositSlipEditor.php?DepositSlipID=".$Id;
    }
    
	function searchMembers($query) 
    {
			$sSearchTerm = $query;
			$sSearchType = "person";
			$fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM `person_per` WHERE per_FirstName LIKE \'%'.$sSearchTerm.'%\' OR per_LastName LIKE \'%'.$sSearchTerm.'%\' OR per_Email LIKE \'%'.$sSearchTerm.'%\' OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%'.$sSearchTerm.'%\' LIMIT 15';
			$result=mysql_query($fetch);
			
	}

	private function validateDate($payment) 
    { 
		// Validate Date
		if (strlen($payment->Date) > 0) {
			list($iYear, $iMonth, $iDay) = sscanf($payment->Date,"%04d-%02d-%02d");
			if ( !checkdate($iMonth,$iDay,$iYear) ) {
				throw new Exception ("Invalid Date");
			}
		}
	
	}
	
	private function validateFund($payment) 
    {
		//Validate that the fund selection is valid:
		//If a single fund is selected, that fund must exist, and not equal the default "Select a Fund" selection.
		//If a split is selected, at least one fund must be non-zero, the total must add up to the total of all funds, and all funds in the split must be valid funds.
		$FundSplit = json_decode($payment->FundSplit);
		if (count($FundSplit) > 1 ) { // split
			$nonZeroFundAmountEntered =0;
			foreach ($FundSplit as $fun_id => $fund) {
				//$fun_active = $fundActive[$fun_id];
				if ($fund->Amount > 0) {
					++$nonZeroFundAmountEntered;
				}
				if ($GLOBALS['bEnableNonDeductible']) {
					//Validate the NonDeductible Amount
					if ($fund->NonDeductible > $fund->Amount) { //Validate the NonDeductible Amount
						throw new Exception (gettext("NonDeductible amount can't be greater than total amount."));
					}
				}
			} // end foreach
			if (!$nonZeroFundAmountEntered) {
				throw new Exception (gettext("At least one fund must have a non-zero amount."));
			}
		}
		elseif (count($FundSplit) ==1 and $FundSplit[0]->FundID != "None")
		{
			//echo "one fund selected ".$FundSplit[0]->FundID;	
		}
		else
		{
			throw new Exception ("Must select a valid fund");
		}
	}
    
	function validateChecks($payment) 
    {
        //validate that the payment options are valid
        //If the payment method is a check, then the check nubmer must be present, and it must not already have been used for this family
        //if the payment method is cash, there must not be a check number
		try {
			if ($payment->type=="Payment" and $payment->iMethod == "CHECK"  and  ! isset($payment->checknumber)) {
				throw new Exception (gettext("Must specify non-zero check number"));
			}
			// detect check inconsistencies
			if ($payment->type=="Payment" and isset($payment->checknumber)) {
				if ($payment->iMethod == "CASH") {
					throw new Exception (gettext("Check number not valid for 'CASH' payment"));
				} 
				//build routine to make sure this check number hasn't been used by this family yet (look at group key)
				/*elseif ($payment->iMethod=='CHECK' and !$sGroupKey) {
					$chkKey = $payment->FamilyID . "|" . $iCheckNo;
					if (array_key_exists($chkKey, $checkHash)) {
						$text = "Check number '" . $iCheckNo . "' for selected family already exists.";
						$sCheckNoError = "<span style=\"color: red; \">" . gettext($text) . "</span>";
						$bErrorFlag = true;
						echo "bErrorFlag 146";
					}
				}*/
			}			
		} catch (Exception $e) {
            throw new Exception ("Error validating check: ".$e->getMessage());
        }		
	}
	
	function processCurrencyDenominations($payment) 
    {
		//Process the Currency Denominations for this deposit
			if ($payment->iMethod  == "CASH"){
				$sGroupKey = genGroupKey("cash", $payment->FamilyID, $fun_id, $dDate);
				foreach ($currencyDenomination2Name as $cdem_denominationID =>$cdem_denominationName)
				{
					$sSQL = "INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, pdem_denominationID, pdem_denominationQuantity) 
					VALUES ('". $sGroupKey ."','".$cdem_denominationID."','".$_POST['currencyCount-'.$cdem_denominationID]."')";
					if (isset ($sSQL)) {
						RunQuery($sSQL);
						unset($sSQL);
					}
				}
			}
		
		
	}
	
	function insertPledgeorPayment ($payment) 
    {
		// Only set PledgeOrPayment when the record is first created
			// loop through all funds and create non-zero amount pledge records
			unset($sGroupKey);
			$FundSplit = json_decode($payment->FundSplit);
			echo "funds selected: ".count($FundSplit);
			print_r($FundSplit);
			foreach ($FundSplit as $Fund) {
				if ($Fund->Amount > 0) {  //Only insert a row in the pledge table if this fund has a non zero amount.
					if (!isset($sGroupKey) )  //a GroupKey references a single familie's payment, and transcends the fund splits.  Sharing the same Group Key for this payment helps clean up reports.
					{
						if ($payment->iMethod  == "CHECK") {
							$sGroupKey = genGroupKey($payment->iCheckNo, $payment->FamilyID, $Fund->FundID, $payment->Date);
						} elseif ($payment->iMethod == "BANKDRAFT") {
							if (!$iAutID) {
								$iAutID = "draft";
							}
							$sGroupKey = genGroupKey($iAutID, $payment->FamilyID, $Fund->FundID, $payment->Date);
						} elseif ($payment->iMethod  == "CREDITCARD") {
							if (!$iAutID) {
								$iAutID = "credit";
							}
							$sGroupKey = genGroupKey($iAutID, $payment->FamilyID, $Fund->FundID, $payment->Date);
						} else {
							$sGroupKey = genGroupKey("cash", $payment->FamilyID, $Fund->FundID, $payment->Date);
						} 
					}
					$sSQL = "INSERT INTO pledge_plg
						(plg_famID,
						plg_FYID, 
						plg_date, 
						plg_amount,
						plg_schedule, 
						plg_method, 
						plg_comment, 
						plg_DateLastEdited, 
						plg_EditedBy, 
						plg_PledgeOrPayment, 
						plg_fundID, 
						plg_depID, 
						plg_CheckNo, 
						plg_scanString, 
						plg_aut_ID, 
						plg_NonDeductible, 
						plg_GroupKey)
						VALUES ('" . 
						$payment->FamilyID . "','" . 
						$payment->FYID . "','" . 
						$payment->Date . "','" .
						$Fund->Amount . "','" . 
						(isset($payment->schedule) ? $payment->schedule : "NULL") . "','" . 
						$payment->iMethod  . "','" . 
						$Fund->Comment . "','".
						date("YmdHis") . "'," .
						$_SESSION['iUserID'] . ",'" . 
						(isset($payment->type) ? "pledge" : "payment") . "'," . 
						$Fund->FundID . "," . 
						$payment->DepositID . "," . 
						(isset($payment->iCheckNo) ? $payment->iCheckNo : "NULL") . ",'" . 
						(isset($payment->tScanString) ? $payment->tScanString : "NULL") . "','" . 
						(isset($payment->iAutID ) ? $payment->iAutID  : "NULL") . "','" . 
						(isset($Fund->nNonDeductible ) ? $Fund->nNonDeductible : "NULL") . "','" . 
						$sGroupKey . "')";
							
						echo "SQL: ".$sSQL;
						
						if (isset ($sSQL)) {
							RunQuery($sSQL);
							unset($sSQL);
						}
				}
			}
	}
	
	function submitPledgeOrPayment($payment) 
    {
        $this->validateFund($payment);
        $this->validateChecks($payment);
        $this->validateDate($payment);
        #echo "Validating deposit".PHP_EOL;
        #$this->validateDeposit($payment);
        $this->insertPledgeorPayment ($payment);
        if ($payment->iMethod =="CASH") {
            $this->processCurrencyDenominations($payment);
        }
				
	}
	
	function getPledgeorPayment($GroupKey) 
    {
		require_once "FamilyService.php";
		$total=0;
		$FamilyService = New FamilyService();
		$sSQL = "SELECT plg_plgID, plg_FamID, plg_date, plg_fundID, plg_amount, plg_NonDeductible,plg_comment, plg_FYID, plg_method, plg_EditedBy from pledge_plg where plg_GroupKey=\"" . $GroupKey . "\"";
		$rsKeys = RunQuery($sSQL);
		$payment=new stdClass();
		$payment->funds = array();
		while ($aRow = mysql_fetch_array($rsKeys)) {
			extract($aRow);
			$payment->Family = $FamilyService->getFamilyStringByID($plg_FamID);
			$payment->Date = $plg_date;
			$payment->FYID = $plg_FYID;
			$payment->iMethod = $plg_method;
			$fund['FundID']=$plg_fundID;
			$fund['Amount']=$plg_amount;
			$fund['NonDeductible']=$plg_NonDeductible;
			$fund['Comment']=$plg_comment ;
			array_push($payment->funds,$fund);
			$total += $plg_amount;
			$onePlgID = $aRow["plg_plgID"];
			$oneFundID = $aRow["plg_fundID"];
			$iOriginalSelectedFund = $oneFundID; // remember the original fund in case we switch to splitting
			$fund2PlgIds[$oneFundID] = $onePlgID;
		}
		$payment->total = $total;
		return json_encode($payment);
		
	}
    
    function getDepositOFX($depID)
    {
        $fundTotal = array ();
        $iDepositComment ="";
        $iDepositSlipID = 0;
        $OFXReturn = new StdClass();
        // Get the Deposit
        $sSQL = "SELECT dep_Date, dep_Comment, dep_Closed, dep_Type FROM deposit_dep WHERE dep_ID= ".$depID;
        $depositRow = RunQuery($sSQL);
        list ($dep_date,$dep_comment,$dep_closed,$dep_type) = mysql_fetch_row($depositRow);
        // Get the list of funds
        $sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
        $rsFunds = RunQuery($sSQL);
        //Get the payments for this deposit slip
        $sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_method, plg_CheckNo, 
                     plg_comment, a.fam_Name AS FamilyName, b.fun_Name AS fundName
                     FROM pledge_plg
                     LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
                     LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
                     WHERE plg_PledgeOrPayment = 'Payment' AND plg_depID = " . $depID . " ORDER BY pledge_plg.plg_method DESC, pledge_plg.plg_date";
        $rsPledges = RunQuery($sSQL);

        // Exit if no rows returned
        $iCountRows = mysql_num_rows($rsPledges);
        if ($iCountRows < 1)
        {
           throw new Exception("No payments on this deposit");
        }
        while ($aRow = mysql_fetch_array($rsPledges))
        {
            extract($aRow);
            if (!$fundName)
            {
                $fundTotal['UNDESIGNATED'] += $plg_amount;
            }
            else 
            {
                if (array_key_exists ($fundName, $fundTotal))
                {
                    $fundTotal[$fundName] += $plg_amount;
                }
                else
                {
                    $fundTotal[$fundName] = $plg_amount;
                }
            }
        }
        $orgName = "ChurchCRM Deposit Data";
        $OFXReturn->content = "OFXHEADER:100".PHP_EOL.
                    "DATA:OFXSGML".PHP_EOL.
                    "VERSION:102".PHP_EOL.
                    "SECURITY:NONE".PHP_EOL.
                    "ENCODING:USASCII".PHP_EOL.
                    "CHARSET:1252".PHP_EOL.
                    "COMPRESSION:NONE".PHP_EOL.
                    "OLDFILEUID:NONE".PHP_EOL.
                    "NEWFILEUID:NONE".PHP_EOL.PHP_EOL;
        $OFXReturn->content .= "<OFX>";
        $OFXReturn->content .= "<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>".date("YmdHis.u[O:T]")."<LANGUAGE>ENG<FI><ORG>".$orgName."<FID>12345</FI></SONRS></SIGNONMSGSRSV1>";
        $OFXReturn->content .= "<BANKMSGSRSV1>".
          "<STMTTRNRS>".
            "<TRNUID>".
            "<STATUS>".
              "<CODE>0".
             "<SEVERITY>INFO".
            "</STATUS>";
                

            if (mysql_num_rows ($rsFunds) > 0) 
            {
                mysql_data_seek($rsFunds,0);
                while ($row = mysql_fetch_array($rsFunds))
                {
                $fun_name = $row["fun_Name"];
                    if (array_key_exists ($fun_name, $fundTotal) && $fundTotal[$fun_name] > 0) 
                    {
                         $OFXReturn->content.="<STMTRS>".
                              "<CURDEF>USD".
                              "<BANKACCTFROM>".
                                "<BANKID>".$orgName.
                                "<ACCTID>".$fun_name.
                                "<ACCTTYPE>SAVINGS".
                             "</BANKACCTFROM>";
                         $OFXReturn->content.=
                        "<STMTTRN>".
                            "<TRNTYPE>CREDIT".
                            "<DTPOSTED>".date("Ymd", strtotime($dep_date)).
                            "<TRNAMT>".$fundTotal[$fun_name].
                            "<FITID>". 
                            "<NAME>".$dep_comment.
                            "<MEMO>".$fun_name.
                        "</STMTTRN></STMTRS>";
                    }
                }
                if (array_key_exists ('UNDESIGNATED', $fundTotal) && $fundTotal['UNDESIGNATED']) 
                {
                        $OFXReturn->content.="<STMTRS>".
                              "<CURDEF>USD".
                              "<BANKACCTFROM>".
                                "<BANKID>".$orgName.
                                "<ACCTID>General".
                                "<ACCTTYPE>SAVINGS".
                             "</BANKACCTFROM>";
                         $OFXReturn->content.=
                        "<STMTTRN>".
                            "<TRNTYPE>CREDIT".
                            "<DTPOSTED>".date("Ymd", strtotime($dep_date)).
                            "<TRNAMT>".$fundTotal[$fun_name].
                            "<FITID>". 
                            "<NAME>".$dep_comment.
                            "<MEMO>General".
                        "</STMTTRN></STMTRS>";
                    
                }	
            }

             $OFXReturn->content.= "
              </STMTTRNRS></BANKTRANLIST></OFX>";
            // Export file
             $OFXReturn->header = "Content-Disposition: attachment; filename=ChurchCRM-Deposit-".$depID."-" . date("Ymd-Gis") . ".ofx";
            return  $OFXReturn;   
                
                
    }
       
}
?>