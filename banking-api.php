<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Http\Controllers\Controller;

use App\Bank\Account;

use App\Bank\Transaction;

class TestController extends Controller
{
    //
}


class BankController extends Controller
{
   public function __construct()
   {
       $this->middleware('auth');
   }

   public function createAccount(Request $request)
   {
       $this->validate($request, [
           'initial_deposit' => 'required|numeric',
           'ID_of_Customers' => 'required|exists:customers,id',
        ]);

       $Account_In_The_Bank = new Account_In_The_Bank;
       $Account_In_The_Bank->ID_of_Customers = $request->ID_of_Customers;
       $Account_In_The_Bank->balance = $request->initial_deposit;
       $Account_In_The_Bank->save();

       return response()->json([
           'message' => 'successfully opened a bank account ',
           'data' => $Account_In_The_Bank,
       ], 201);
   }

   public function transfer(Request $request)
   {
       $this->validate($request, [
           'ID_From_Account' => 'required|exists:bank_accounts,id',
           'ID_To_Account' => 'required|exists:bank_accounts,id',
           'amount' => 'required|numeric',
       ]);

       if ($request->ID_From_Account == $request->ID_To_Account) {
           return response()->json([
               'message' => 'Transfers cannot be made to the same account. ',
           ], 400);
       }

       $fromAccount_In_The_Bank = Account_In_The_Bank::find($request->ID_From_Account);
       $toAccount_In_The_Bank = Account_In_The_Bank::find($request->ID_To_Account);

       if ($fromAccount_In_The_Bank->balance < $request->amount) {
           return response()->json([
               'message' => 'Insufficient funds',
           ], 400);
       }

       $fromAccount_In_The_Bank->balance -= $request->amount;
       $toAccount_In_The_Bank->balance += $request->amount;
       $fromAccount_In_The_Bank->save();
       $toAccount_In_The_Bank->save();

       $bankTransaction = new BankTransaction;
       $bankTransaction->ID_From_Account = $request->ID_From_Account;
       $bankTransaction->ID_To_Account = $request->ID_To_Account;
       $bankTransaction->amount = $request->amount;
       $bankTransaction->save();

       return response()->json([
           'message' => 'Successful transfer ',
           'data' => $bankTransaction,
       ], 200);
   }

   public function getBalance(Request $request, $account_id)
   {
       $this->validate($request, [
           'account_id' => 'required|exists:bank_accounts,id',
       ]);

       $Account_In_The_Bank = Account_In_The_Bank::find($account_id);

       return response()->json([
           'message' => 'Balance retrieved successfully',
           'data' => [
               'account_id' => $Account_In_The_Bank->id,
               'ID_of_Customers' => $Account_In_The_Bank->ID_of_Customers,
               'balance' => $Account_In_The_Bank->balance,
           ],
       ], 200);
   }

   public function getTransferHistory(Request $request, $account_id)
   {
       $this->validate($request, [
           'account_id' => 'required|exists:bank_accounts,id',
       ]);

       $bankTransactions = BankTransaction::where('ID_From_Account', $account_id)
           ->orWhere('ID_To_Account', $account_id)
           ->get();

       return response()->json([
           'message' => 'Transfer history retrieved successfully',
           'data' => $bankTransactions,
       ], 200);
   }
}


    //Practical Example

<?php

namespace TestsFeature;

use Tests\Test\Case;
use Illuminate\Foundation\Testing\Without\Middleware;
use Illuminate\Foundation\Testing\Database\Migrations;
use Illuminate\Foundation\Testing\Database\Transactions;

use App\Bank\Transaction;
use App\Bank\Account;


class BankingAPI extends TestCase
{
   use DatabaseTransactions;

   public function setUp()
   {
       parent::setUp();

       $this->Account_In_The_Bank = factory(Account_In_The_Bank::class)->create();
   }

   
   public function New_Bank_Account()
   {
       $data = [
           'ID_of_Customers' => 1,
           'initial_deposit' => 1000,
       ];

       $this->json('POST', '/api/bank/account', $data)
           ->assertStatus(201)
           ->assertJson([
               'message' => 'Successfully opened a bank account ',
           ]);
   }

   
     
   public function Account_Balance()
   {
       $this->json('GET', '/api/bank/account/' . $this->Account_In_The_Bank->id . '/balance')
           ->assertStatus(200)
           ->assertJson([
               'message' => 'Account Balance obtained',
               'data' => [
                   'account_id' => $this->Account_In_The_Bank->id,
                   'ID_of_Customers' => $this->Account_In_The_Bank->ID_of_Customers,
                   'balance' => $this->Account_In_The_Bank->balance,
               ],
           ]);
   }


public function Accounts_Transaction_History()
   {
       $bankTransaction = factory(BankTransaction::class)->create([
           'ID_From_Account' => $this->Account_In_The_Bank->id,
       ]);

       $this->json('GET', '/api/bank/account/' . $this->Account_In_The_Bank->id . '/history')
           ->assertStatus(200)
           ->assertJson([
               'message' => 'Transfer history obtained',
               'data' => [
                   [
                       'id' => $bankTransaction->id,
                       'ID_From_Account' => $bankTransaction->ID_From_Account,
                       'ID_To_Account' => $bankTransaction->ID_To_Account,
                       'amount' => $bankTransaction->amount,
                       'created_at' => $bankTransaction->created_at->toDateTimeString(),
                       'updated_at' => $bankTransaction->updated_at->toDateTimeString(),
                   ],
               ],
           ]);
   }
}

public function Money_Transfers()
   {
       $fromAccount_In_The_Bank = factory(Account_In_The_Bank::class)->create([
           'balance' => 2000,
       ]);
       $toAccount_In_The_Bank = factory(Account_In_The_Bank::class)->create();

       $data = [
           'ID_From_Account' => $fromAccount_In_The_Bank->id,
           'ID_To_Account' => $toAccount_In_The_Bank->id,
           'amount' => 1000,
       ];

       $this->json('POST', '/api/bank/transfer', $data)
           ->assertStatus(200)
           ->assertJson([
               'message' => 'Transfer Complete',
           ]);

       $this->assertDatabaseHas('bank_transactions', [
           'ID_From_Account' => $fromAccount_In_The_Bank->id,
           'ID_To_Account' => $toAccount_In_The_Bank->id,
           'amount' => 1000,
       ]);

       $this->assertDatabaseHas('bank_accounts', [
           'id' => $fromAccount_In_The_Bank->id,
           'balance' => 1000,
       ]);

       $this->assertDatabaseHas('bank_accounts', [
           'id' => $toAccount_In_The_Bank->id,
           'balance' => 1000,
       ]);
   }
   
   