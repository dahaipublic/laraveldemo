<?php
namespace App\Libs;

use Excel;

class Tool
{
    public static function getTree($items)
    {
        $tree = array(); //格式化好的树
        foreach ($items as $item)
            if (isset($items[$item['parent_id']]))
                $items[$item['parent_id']]['son'][] = &$items[$item['id']];
            else
                $tree[] = &$items[$item['id']];
        $sort = collect($tree)->sortBy('order_id')->values()->all();
        return $sort;
    }

    public static function exportExcel($filename, $data)
    {
        Excel::create($filename, function($excel) use ($data){
            $excel->sheet('score', function($sheet) use ($data){
                $sheet->rows($data);
            });
        })->export('xls');
    }
}