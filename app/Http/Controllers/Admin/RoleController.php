<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Role;
use App\Models\Admin\RolePermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\RoleUser;
use App\Models\Admin\User;
use App\Models\Admin\Permission;
use App\Models\Admin\SystemOperationLog;
/**
 * @group 3超级管理员子用户角色操作
 * - author llc
 * 
 */
class RoleController extends Controller
{
    /**
     * 3.1.角色列表
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string |CSRF TOKEN    |
    |page |是  |int |页码,默认为1   |
    |count |否  |int |页码,默认为15   |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "成功",
    "data": {
    "0": {
    "id": 3,
    "name": "店小三",
    "permission": [
    {
    "id": 1,
    "name": "控制台"
    },
    {
    "id": 2,
    "name": "客户管理"
    }
    ]
    },
    "1": {
    "id": 4,
    "name": "老板二号",
    "permission": [
    {
    "id": 1,
    "name": "控制台"
    },
    {
    "id": 2,
    "name": "客户管理"
    }
    ]
    },
    "current_page": 1,
    "next_page_url": null,
    "previous_pageUrl": null,
    "total": 2,
    "perPage": 15,
    "first_page": "http://tradepost.com/adm/adrolelist?page=1"
    }
    }
    ```
     **返回参数说明**
    |参数名|类型|说明|
    |:-----  |:-----|-----                           |

     * */    
    public function getRoles(Request $request)
    {

        $validator = Validator::make($request->all(), [
           //'name'     =>'nullable|string',
            'page'        =>'nullable|integer',
            'count'       =>'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        $page = intval($request->input('page',1));
        $count = intval($request->input('count',15));

        $id = Auth::guard('admin')->user()->id;

        $paginate = Role::with('permissions')->select('id','name')->paginate($count);


        // foreach ($paginate as $k=>$role) {
            
        // }

        foreach ($paginate as $k=>$role) {
            // $temp["id"] = $role->id; 
            // $temp["name"] = $role->name;
            // $rst['data'][$k] = $temp;
            $ptemp[] = '';
            if (!empty($role->permissions)){

                 //为了给前端好做，去掉父菜单
                foreach ($role->permissions as $key => &$value) {
//                    if ($value->pid==0 && $value->pcnum!==0) {
                    if ($value->is_menu==2 && $value->pcnum!==0) {
                        $temp = 0;
                        foreach ($role->permissions as $k => $v) {
//                            if ($v->is_menu==2 && $v->pid==$value->id) {
                            if ($v->is_p==2 && $v->pid==$value->id) {
                                $temp +=1;
                            }   
                        }

                        if ($temp<$value->pcnum) {
//                            $value->is_menu = 0;
                            $value->is_p = 0;
                        }
                    }
                }

                
                foreach ($role->permissions as $permission) {
//                    if ($permission->is_menu==2) {
                    if ($permission->is_p==2) {
                        $ptemp[] = array('id'=>$permission->id,'name'=>$permission->name_cn);
                    }
                }

            }
            $ptemp = array_filter($ptemp);
            
            $ptemp = collect($ptemp)->unique('id')->toArray();
            $rtemp["id"] = $role->id; 
            $rtemp["name"] = $role->name;
            $rtemp['permission'] = $ptemp;
            $rst[] = $rtemp;
            
            unset($ptemp);
        }


        $rst['current_page'] = $paginate->currentPage();
        $rst['next_page_url'] = $paginate->nextPageUrl();
        $rst['previous_pageUrl'] = $paginate->previousPageUrl();
        $rst['total'] = $paginate->total();
        $rst['perPage'] = $paginate->perPage();
        $rst['first_page'] = $paginate->url(1);
        
        return response_json(200,trans('web.success'),$rst);

    }
    /**
     * 3.2.添加角色
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string |CSRF TOKEN    |
    |name |是  |string |角色名称    |
    |ids |是  |数组 |权限id    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "添加数据成功",
    "data": {
    "0": {
    "id": 1,
    "name": "控制台"
    },
    "1": {
    "id": 2,
    "name": "客户管理"
    },
    "roleId": 4,
    "roleName": "老板二号"
    }
    }
    ```
     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |

     * */
    public function roleAdd(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string',
            'ids' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        $id = Auth::guard('admin')->user()->id;

        //  新建角色
        $data = $request->only(['rolename', 'permissionid']);

        try {
            DB::beginTransaction();
            $role = new Role;
            $role->name = $request->input('name');
            $rst1 = $role->save();
            $roleId = $role->id;
            //有某一个菜单的子权限，就加这个菜单栏
            $pmenu = array();
            foreach($request->input('ids') as $k=>$v){
                if (!empty($v)) {
                
                    $pPermission = Permission::where('id',$v)->first();
                    $pPermi[] = ['name'=>$pPermission->name,'name_cn'=>$pPermission->name_cn,'name_hk'=>$pPermission->name_hk];
                    //菜单栏权限
//                     if ($pPermission->is_menu==2 && $pPermission->pid!==0) {
                     if ($pPermission->is_p==2 && $pPermission->pid!==0) {
                        $parentMenu = Permission::where('id',$pPermission->pid)->first();
                        if (!empty($parentMenu)) {
                            $pmenu[] = ['permission_id'=>$parentMenu->id,'role_id'=>$roleId];
                        }

                     }

                    //父菜单下的子菜单
                    $subpermission = Permission::where('pid',$v)->get();

                    if(!empty($subpermission)){
                        foreach ($subpermission as $key => $value) {
                            $temp['permission_id'] = $value->id;
                            $temp['role_id'] = $roleId;
                            $insertData[] = $temp;
                        }
                    }
                    $temp['permission_id'] = $v;
                    $temp['role_id'] = $roleId;
                    $insertData[] = $temp;


                }
                
            }
            
            $pmenu = collect($pmenu)->unique('permission_id')->toArray();

            foreach ($pmenu as $key => $value) {

                $insertData[] = $value;
            }
            //去掉重复值
            $insertData = collect($insertData)->unique('permission_id')->toArray();
            $rst2 = RolePermission::insert($insertData);
            foreach ($role->permissions as $permission) {
                if ($permission->is_menu==2) {
                    $permissions['permission'][] = array('id'=>$permission->id,'name'=>$permission->name_cn);
                }
            }
            $permissions['menu'] =  $pPermi;
            $permissions['roleId'] =  $roleId;
            $permissions['roleName'] =  $role->name;
           
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("Admin AddUser fail:".$request->input('_token')."Admin AddUser: exeception".$exception);
            return response_json(402,trans('web.addFail'));
        }

         if ($rst1 && $rst2) {
            DB::commit();
             $admin = Auth('admin')->user();
             //记录日志，管理员添加活动记录
             if ($admin->language == 'cn'){
                 $msg = '管理员'.$admin->username."添加角色：".$request->input('name');;
             }elseif($admin->language == 'en'){
                 $msg = 'Administrators '.$admin->username." add role：".$request->input('name');;
             }elseif($admin->language == 'hk'){
                 $msg = '管理员'.$admin->username."添加角色：".$request->input('name');;
             }
             SystemOperationLog::add_log($admin->id,$request,$msg);
            return response_json(200,trans('web.addSuccess'),$permissions);
         }else{
            return response_json(402,trans('web.addFail'));
         }
    }
    /**
     * 3.3.角色详情
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string |CSRF TOKEN    |
    |roleid |是  |string |角色id    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": {
    "permission": [
    {
    "id": 1,
    "name": "控制台"
    },
    {
    "id": 2,
    "name": "客户管理"
    }
    ],
    "name": "店小三",
    "id": 3
    }
    }
    ```
     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |

     * */
    public function getRoledetail(Request $request)
    {

        $inputP = $input=$request->all();
        $validator = Validator::make($request->all(),[
            'id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        $role =  Role::with('permissions')->where('id',$request->input('id'))->first();

        if (!empty($role)){
            if (!$role->permissions->isEmpty()){

                //为了给前端好做，去掉父菜单
                foreach ($role->permissions as $key => &$value) {
//                    if ($value->pid==0 && $value->pcnum!==0) {
                    if ($value->is_menu==2 && $value->pcnum!==0) {
                        $temp = 0;
                        foreach ($role->permissions as $k => $v) {
//                            if ($v->is_menu==2 && $v->pid==$value->id) {
                            if ($v->is_p==2 && $v->pid==$value->id) {
                                $temp +=1;
                            }   
                        }

                        if ($temp<$value->pcnum) {
//                            $value->is_menu = 0;
                            $value->is_p = 0;
                        }
                    }
                }
                foreach ($role->permissions as $permission) {
//                    if ($permission->is_menu==2) {
                    if ($permission->is_p==2) {
                        $permissions[] = array('id' => $permission->id, 'name' => $permission->name_cn);
                    }
                }



                $roleRst['permission'] = $permissions;
            }else{
                $roleRst['permission'] = null;
            }
            //$roleRst['name'] = ['name'=>$role->name,'name_cn'=>$role->name_cn,'name_hk'=>$role->name_hk];
            $roleRst['name'] = $role->name;
            $roleRst['id'] = $role->id;
        }else{
            $roleRst = null;
        }

        return response_json(200,trans('web.getDataSuccess'),$roleRst);
    }


    /**
     * 3.4.更新角色
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string |CSRF TOKEN    |
    |id |是  |string |角色ID    |
    |ids |是  |数组 |权限id    |
    |name |是  |string |角色名称    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "更新数据成功",
    "data": {
    "0": {
    "id": 2,
    "name": "客户管理"
    },
    "roleId": 1,
    "roleName": "二管理员"
    }
    }
    ```
     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |

     * */
    public function updateRole(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required|int',
            'ids' => 'required|array',
            'name' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        $id = Auth::guard('admin')->user()->id;

        //  更新角色
        $data = $request->only(['rolename', 'ids']);

        try {
            DB::beginTransaction();
            $role = Role::where('id',$request->input('id'))->first();
            if (empty($role)) {
                DB::rollBack();
                return response_json(402,trans('web.updateFail'));
            }
            if ($request->input('name')) {
                $role->name = $request->input('name');
            }
            $rst1 = $role->save();
            $roleId = $role->id;

            $rst2 = RolePermission::where('role_id',$request->input('id'))->delete();
            if ($request->input('ids')) {

                $pmenu = array();
                foreach($request->input('ids') as $k=>$v){
                    if (!empty($v)) {
                    
                        $pPermission = Permission::where('id',$v)->first();
                        $pPermi[] = ['name'=>$pPermission->name,'name_cn'=>$pPermission->name_cn,'name_hk'=>$pPermission->name_hk];

//                         if ($pPermission->is_menu==2 && $pPermission->pid!==0) {
                         if ($pPermission->is_p==2 && $pPermission->pid!==0) {
                            $parentMenu = Permission::where('id',$pPermission->pid)->first();

                             if ($parentMenu->id==14){                 //商家列表加商家管理
                                 $pmenu[] = ['permission_id'=>2,'role_id'=>$roleId];
                             }
                            if (!empty($parentMenu)) {
                                $pmenu[] = ['permission_id'=>$parentMenu->id,'role_id'=>$roleId];
                            }
                        }
                        $subpermission = Permission::where('pid',$v)->get();
                        if(!empty($subpermission)){
                            foreach ($subpermission as $key => $value) {
                                $temp['permission_id'] = $value->id;
                                $temp['role_id'] = $roleId;
                                $insertData[] = $temp;
                            }
                        }
                        $temp['permission_id'] = $v;
                        $temp['role_id'] = $roleId;
                        $insertData[] = $temp;
                    
                      
                    }
                    
                }
                
                $pmenu = collect($pmenu)->unique('permission_id')->toArray();


                foreach ($pmenu as $key => $value) {
                    $insertData[] = $value;
                }
                //去掉重复值
                $insertData = collect($insertData)->unique('permission_id')->toArray();
                $rst3 = RolePermission::insert($insertData);


            }
            foreach ($role->permissions as $permission) {
                if ($permission->is_menu==2) {
                    $permissions['permission'][] = array('id'=>$permission->id,'name'=>$permission->name_cn);
                }
            }
            $permissions['menu'] =  $pPermi;
            $permissions['roleId'] =  $roleId;
            $permissions['roleName'] =  $role->name;

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("Admin UpdateRole fail:".$request->input('_token')."Admin AddUser: exeception".$exception);
            return response_json(402,trans('web.updateFail'));
        }

        if ($rst1 && $rst2) {
            DB::commit();
            $admin = Auth('admin')->user();
            //记录日志，管理员添加活动记录
            if ($admin->language == 'cn'){
                $msg = '管理员'.$admin->username."更新角色：".$request->input('name');;
            }elseif($admin->language == 'en'){
                $msg = 'Administrators '.$admin->username." add role：".$request->input('name');;
            }elseif($admin->language == 'hk'){
                $msg = '管理员'.$admin->username."更新角色：".$request->input('name');;
            }
            SystemOperationLog::add_log($admin->id,$request,$msg);
            return response_json(200,trans('web.updateSuccess'),$permissions);
        }else{
            return response_json(402,trans('web.updateFail'));
        }
    }

    /**
     * 3.5.删除角色
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string |CSRF TOKEN    |
    |id |是  |string |角色ID    |


     **返回示例**

    ```

    ```
     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |

     * */
    public function delRole(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required|int',
        ]);

        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $rst1 = Role::where("id",$request->input('id'))->delete();

            $rst2 = RolePermission::where("role_id",$request->input('id'))->delete();

            $roleuser = RoleUser::where("role_id",$request->input('id'))->get();
            if (!empty($roleuser)) {
                foreach ($roleuser as $key => $value) {
                    $rst3 = User::where("id",$value['user_id'])->delete();
                }
            }
            $rst4 = RoleUser::where("role_id",$request->input('id'))->delete();

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("Admin DeleteUser fail:".$request->input('token')."Admin AddUser: exeception".$exception);
            return response_json(402,trans('web.deleteFail'));
        }

        if ($rst1 && $rst2) {
            DB::commit();
            $admin = Auth('admin')->user();
            //记录日志，管理员添加活动记录
            if ($admin->language == 'cn'){
                $msg = '管理员'.$admin->username."添加角色：".$request->input('id');;
            }elseif($admin->language == 'en'){
                $msg = 'Administrators '.$admin->username." add role：".$request->input('id');;
            }elseif($admin->language == 'hk'){
                $msg = '管理员'.$admin->username."添加角色：".$request->input('id');;
            }
            SystemOperationLog::add_log($admin->id,$request,$msg);
            return response_json(200,trans('web.deleteSuccess'));
        }else{
            return response_json(402,trans('web.deleteFail'));
        }
    }
}
