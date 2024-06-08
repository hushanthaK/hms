<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth, DB, Hash;
use App\Company;
use Excel;

class CompanyController extends Controller
{
    private $paginate = 10;
    private $core;
    public $data = [];
    public function __construct()
    {
        $this->core = app(\App\Http\Controllers\CoreController::class);
        $this->middleware('auth');
    }

    public function addCompany()
    {
        return view('backend/companies/add_edit', $this->data);
    }

    public function editCompany(Request $request)
    {
        $this->data['data_row'] = Company::whereId($request->id)->where('is_deleted', 0)->first();
        if (!$this->data['data_row']) {
            return redirect()->back()->with(['error' => config('constants.FLASH_REC_NOT_FOUND')]);
        }
        return view('backend/companies/add_edit', $this->data);
    }

    public function saveCompany(Request $request)
    {
        if ($request->id > 0) {
            if ($this->core->checkWebPortal() == 0) {
                return redirect()->back()->with(['info' => config('constants.FLASH_NOT_ALLOW_FOR_DEMO')]);
            }
            $success = config('constants.FLASH_REC_UPDATE_1');
            $error = config('constants.FLASH_REC_UPDATE_0');
        } else {
            $success = config('constants.FLASH_REC_ADD_1');
            $error = config('constants.FLASH_REC_ADD_0');
        }
        $res = Company::updateOrCreate(['id' => $request->id], $request->except(['_token']));
        if ($res) {

            //sync user and customer
            // $this->core->syncUserAndCustomer();

            return redirect()->route('list-company')->with(['success' => $success]);
        }
        return redirect()->back()->with(['error' => $error]);
    }

    public function listCompany()
    {
        $this->data['datalist'] = Company::where('is_deleted', 0)->orderBy('name', 'ASC')->get();
        $this->data['company_list'] = getCompanyList('get');
        $this->data['search_data'] = ['customer_id' => '', 'mobile_num' => '', 'city' => '', 'state' => '', 'country' => ''];
        return view('backend/companies/list', $this->data);
    }

    public function searchCompany(Request $request)
    {
        $query = Company::where('is_deleted', 0)->orderBy('name', 'ASC');
        if ($request->company_id) {
            $query->where('id', $request->company_id);
        }
        if ($request->mobile_num) {
            $query->where('mobile', $request->mobile_num);
        }
        if ($request->city) {
            $query->where('city', $request->city);
        }
        if ($request->state) {
            $query->where('state', $request->state);
        }
        if ($request->country) {
            $query->where('country', $request->country);
        }

        $this->data['datalist'] = $query->get();
        $this->data['company_list'] = getCompanyList('get');
        $this->data['search_data'] = $request->all();

        if ($request->submit_btn == 'export') {
            // $params = ['data' => $this->data['datalist'], 'view' => 'excel_view.customer_excel'];
            // $filename = 'customers.xlsx';
            // return Excel::download(new CheckoutExport($params), $filename);
        } else {
            return view('backend/companies/list', $this->data);
        }
    }

    public function deleteCompany(Request $request) {
        if($this->core->checkWebPortal()==0){
            return redirect()->back()->with(['info' => config('constants.FLASH_NOT_ALLOW_FOR_DEMO')]);
        }  
        if(Company::whereId($request->id)->update(['is_deleted'=>1])){
            return redirect()->back()->with(['success' => config('constants.FLASH_REC_DELETE_1')]);
        }
        return redirect()->back()->with(['error' => config('constants.FLASH_REC_DELETE_0')]);
    }
}
