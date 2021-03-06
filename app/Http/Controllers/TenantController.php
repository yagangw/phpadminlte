<?php

namespace App\Http\Controllers;

use Excel;
use App\User;
use App\Tenant;
use Illuminate\Http\Request;
use Ultraware\Roles\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class TenantController extends Controller
{
    /*
     * Response of fileinput
     */
     protected $id = 0;
    
    /*
     * Read and Display all records
     */
    public function index()
    {
        if (Auth::user()->hasRole('admin')){
            $result = Tenant::all();
        }elseif(Auth::user()->hasRole('moderator')){
            $tenantID = Auth::user()->tenant_id;
            $result = array(0 => DB::table('tenants')->where('id', $tenantID)
                        ->first());
        }else{}
        return view('tenant')->with('data',$result);
    }

    /*
     * Wizard
     */
    public function wizard(Request $request)
    {
        try{
            $tenant = new Tenant;
            $tenant->name                 = $request->name;
            $tenant->desc	              = $request->desc;	
            $tenant->access_number        = $request->access_number;
            $tenant->extrinsic_number     = $request->extrinsic_number;
            $tenant->gateway              = $request->gateway;
            $tenant->prefix               = $request->prefix;
            $tenant->welcome_file         = $request->welcome_file;
            $tenant->nonwork_file         = $request->nonwork_file;
            $tenant->moh_file             = $request->moh_file;
            $tenant->blacklist_on         = ($request->limit==1)?1:0;
            $tenant->whitelist_on         = ($request->limit==-1)?1:0;
            $tenant->call_rate            = $request->call_rate;
            $tenant->call_package         = $request->call_package;
            $tenant->call_package_amount  = $request->call_package_amount;
            $tenant->call_package_minutes = $request->call_package_minutes;
            $tenant->status               = $request->status;
            $tenant->save();
        }catch(\Illuminate\Database\QueryException $e){
            return response()->json(['message' => $e->getMessage()], 406);
        }
        
        $this->id = $tenant->id;
        
        //日期时间
        foreach($request->week_day as $value){
            DB::table('tenantmeta')->insert(
                array('tenant_id' => $this->id, 'meta_key' => 'week_day', 'meta_value' => $value, 'created_at' => \Carbon\Carbon::now(),  'updated_at' => \Carbon\Carbon::now())
            );
        }
        foreach($request->work_hour as $value){
            DB::table('tenantmeta')->insert(
                array('tenant_id' => $this->id, 'meta_key' => 'work_hour', 'meta_value' => $value, 'created_at' => \Carbon\Carbon::now(),  'updated_at' => \Carbon\Carbon::now())
            );
        }
        foreach(explode(",", $request->holiday) as $value){
            DB::table('tenantmeta')->insert(
                array('tenant_id' => $this->id, 'meta_key' => 'holiday', 'meta_value' => $value, 'created_at' => \Carbon\Carbon::now(),  'updated_at' => \Carbon\Carbon::now())
            );
        }
        
        //呼叫限制-黑/白名单
        if($request->limit == 1){//Black
            foreach($request->limit_list as $value){
                DB::table('tenantmeta')->insert(
                    array('tenant_id' => $this->id, 'meta_key' => 'blacklist_number', 'meta_value' => $value, 'created_at' => \Carbon\Carbon::now(),  'updated_at' => \Carbon\Carbon::now())
                );
            }
        }elseif($request->limit == -1){//white[-1]
            foreach($request->limit_list as $value){
                DB::table('tenantmeta')->insert(
                    array('tenant_id' => $this->id, 'meta_key' => 'whilelist_number', 'meta_value' => $value, 'created_at' => \Carbon\Carbon::now(),  'updated_at' => \Carbon\Carbon::now())
                );
            }
        }
        
        //分机Bat
        $count = $request->ext_count;
        for ($i = 0; $i < $count; $i++) {
            $number = Config::get('constants.extension.min') + Config::get('constants.default.subsection')*$this->id + $i;
            DB::table('extensions')->insert(
                array('number' => $number, 'password' => bcrypt($request->ext_password), 'alias_number' => $request->extrinsic_number, 'tenant_id' => $this->id, 'created_at' => \Carbon\Carbon::now(),  'updated_at' => \Carbon\Carbon::now())
            );
        }
        
        
        //用户Bat
        $uploads = realpath(public_path('uploads'));
        $target = $uploads. DIRECTORY_SEPARATOR . $request->user_file;
        
        Excel::load($target, function($reader) {
            $reader->takeRows(Config::get('constants.default.subsection'));
            $reader->takeColumns(Config::get('constants.tpl.user_column'));
            $reader->each(function($sheet) {
                $sheet->each(function($row, $index) {
                    if(empty($row->name))return;
                    $number = Config::get('constants.extension.min') + Config::get('constants.default.subsection')*$this->id + $index;
                    $extension = DB::table('extensions')->select('id','tenant_id')
                                    ->where('number', $number)
                                    ->first();
                    $user = new User;
                    $user->name          = $row->name;
                    $user->fullname	     = $row->fullname;	
                    $user->email         = $row->email;
                    $user->password      = bcrypt($row->password);
                    $user->extension_id  = $extension->id;
                    $user->tenant_id     = $extension->tenant_id;
                    $user->save();
                   
                    //attaching roles
                    $role = Role::where('description', $row->role)->first();
                    $user->attachRole($role->id);
                });
            });
        });
        
        $result = Tenant::all();
        return response()->json($result);
    }
    
    /*
     * Create record
     */
    public function create(Request $request)
    {
        try{
            $tenant = new Tenant;
            $tenant->name                 = $request->name;
            $tenant->desc	              = $request->desc;	
            $tenant->access_number        = $request->access_number;
            $tenant->extrinsic_number     = $request->extrinsic_number;
            $tenant->gateway              = $request->gateway;
            $tenant->prefix               = $request->prefix;
            $tenant->welcome_file         = $request->welcome_file;
            $tenant->nonwork_file         = $request->nonwork_file;
            $tenant->moh_file             = $request->moh_file;
            $tenant->blacklist_on         = $request->blacklist_on;
            $tenant->whitelist_on         = $request->whitelist_on;
            $tenant->call_rate            = $request->call_rate;
            $tenant->call_package         = $request->call_package;
            $tenant->call_package_amount  = $request->call_package_amount;
            $tenant->call_package_minutes = $request->call_package_minutes;
            $tenant->status               = $request->status;
            $tenant->save();
        }catch(\Illuminate\Database\QueryException $e){
            return response()->json(['message' => $e->getMessage()], 406);// 406: Not Acceptable
        }
        
        $result = Tenant::all();
        return response()->json($result);
    }
    
    /*
     * Read record
     */
    public function read(Request $request)
    {
        if($request->ajax()){
            if(isset($request->id)){
                $id = $request->id;
                $result = Tenant::find($id);
            }else{
                if (Auth::user()->hasRole('admin')){
                    $result = Tenant::all();
                }else{
                    $tenantID = Auth::user()->tenant_id;
                    $result = Tenant::find($tenantID);
                }
            }
            
            return response()->json($result);
        }
    }

    /*
     * Update record
     */
    public function update(Request $request)
    {
        $id = $request->id;
        $tenant = Tenant::find($id);
        $tenant->name                 = $request->name;
        $tenant->desc	              = $request->desc;	
        $tenant->access_number        = $request->access_number;
        $tenant->extrinsic_number     = $request->extrinsic_number;
        $tenant->gateway              = $request->gateway;
        $tenant->prefix               = $request->prefix;
        $tenant->welcome_file         = $request->welcome_file;
        $tenant->nonwork_file         = $request->nonwork_file;
        $tenant->moh_file             = $request->moh_file;
        $tenant->blacklist_on         = $request->blacklist_on;
        $tenant->whitelist_on         = $request->whitelist_on;
        $tenant->call_rate            = $request->call_rate;
        $tenant->call_package         = $request->call_package;
        $tenant->call_package_amount  = $request->call_package_amount;
        $tenant->call_package_minutes = $request->call_package_minutes;
        $tenant->status               = $request->status;
        $tenant->save();
        
        $result = Tenant::all();
        return response()->json($result);
    }

    /*
     * Delete record
     */
    public function delete(Request $request)
    {
        $id = $request->id;
        $tenant = Tenant::find($id);
        $result = $tenant->delete();
        if($result)
            echo trans('adminlte_lang::message.crudcolumns.succ');
        else
            echo trans('adminlte_lang::message.crudcolumns.fail');
    }
}
