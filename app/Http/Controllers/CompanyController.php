<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Auth,DB,Hash;
use App\Company;
class CompanyController extends Controller
{
    private $paginate=10;
    private $core;
    public $data=[];
    public function __construct()
    {
        $this->core=app(\App\Http\Controllers\CoreController::class);
        $this->middleware('auth');
    }

    public function addCompany() {
        return view('backend/companies/add_edit',$this->data);
    }
}