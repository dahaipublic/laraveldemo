<?php
namespace App\Libs;

use Excel;

class UploadTool{

    public $upload_name;                    //上传文件名
    public $upload_tmp_name;                //上传临时文件名
    public $upload_final_name;              //上传文件的最终文件名
    public $upload_target_dir;              //文件被上传到的目标目录
    public $upload_target_path;             //文件被上传到的最终路径
    public $upload_file_ext ;               //上传文件类型
    public $allow_uploadedfile_type;        //允许的上传文件类型
    public $upload_file_size;               //上传文件的大小
    public $allow_uploaded_maxsize=10000000;//允许上传文件的最大值，单位为字节，现在相当于允许上传 10M 的文件

    //构造函数
    public function __construct($file_type = "image")
    {
        $this->upload_name = $_FILES[$file_type]["name"]; //取得上传文件名
        $this->upload_filetype = $_FILES[$file_type]["type"];
        $this->upload_tmp_name = $_FILES[$file_type]["tmp_name"];
        $this->upload_file_size = $_FILES[$file_type]["size"];
        $this->upload_target_dir = storage_path('app/public/');
    }


    //文件上传
    public function upload_file()
    {
        if(!empty($_FILES)){
            $upload_file_ext = $this->getFileExt($this->upload_name);//获取文件扩展名
                if($this->upload_file_size < $this->allow_uploaded_maxsize)//判断文件大小是否超过允许的最大值
                {
                    if(!is_dir($this->upload_target_dir))//如果文件上传目录不存在
                    {
                        mkdir($this->upload_target_dir,true);//创建文件上传目录
                        chmod($this->upload_target_dir,0777);//改权限
                    }
                    $this->upload_final_name = date("YmdHis").uniqid().'.'.$upload_file_ext;//生成随机文件名
                    $this->upload_target_path = $this->upload_target_dir."/".$this->upload_final_name;//文件上传目标目录
                    if(!move_uploaded_file($this->upload_tmp_name,$this->upload_target_path))//文件移动失败
                    {
                        return array(
                            'code' => 403,
                            'msg' => '文件上传失败',
                        );
                    }
                    else
                    {
                        return array(
                            'code' => 200,
                            'msg' => '文件上传成功',
                            'data' => array(
                                'img' => $this->upload_final_name
                            )
                        );
                    }
                }
                else
                {
                    return array(
                        'code' => 403,
                        'msg' => '文件太大,上传失败！',
                    );
                }
        }else{
            return array(
                'code' => 402,
                'msg' => '请上传文件'
            );
        }

    }
    /**
     *获取文件扩展名
     *@param String $filename 要获取文件名的文件
     */
    public function getFileExt($filename){
        $info = pathinfo($filename);
        return @$info["extension"];
    }
}


