<?php
namespace App\Libs;


use mpdf\mpdf;

/**
 * https://www.cnblogs.com/blog-dyn/p/7127748.html
 * 将html内容转换为image图片
 *   
 * 结果：可保存、可直接显示。 
 */  
class GenerateImage
{

    //生成pdf
    public function htmlToPdf($html='',$title="标题",$fileName=""){
        $pdf=new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        // 设置打印模式
        //设置文件信息，头部的信息设置
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor("作者");
        $pdf->SetTitle($title);
        $pdf->SetSubject('TCPDF Tutorial');
        $pdf->SetKeywords('TCPDF, PDF, example, test, guide');//设置关键字
        // 是否显示页眉
        $pdf->setPrintHeader(false);
        // 设置页眉显示的内容
        $pdf->SetHeaderData('logo.png', 60, 'owndraw.com', '', array(0,64,255), array(0,64,128));
        // 设置页眉字体
        $pdf->setHeaderFont(Array('deja2vusans', '', '12'));
        // 页眉距离顶部的距离
        $pdf->SetHeaderMargin('5');
        // 是否显示页脚
        $pdf->setPrintFooter(true);
        // 设置页脚显示的内容
        $pdf->setFooterData(array(0,64,0), array(0,64,128));
        // 设置页脚的字体
        $pdf->setFooterFont(Array('dejavusans', '', '10'));
        // 设置页脚距离底部的距离
        $pdf->SetFooterMargin('10');
        // 设置默认等宽字体
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        // 设置行高
        $pdf->setCellHeightRatio(1);
        // 设置左、上、右的间距
        $pdf->SetMargins('10', '10', '10');
        // 设置是否自动分页 距离底部多少距离时分页
        $pdf->SetAutoPageBreak(TRUE, '15');
        // 设置图像比例因子
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage("A4","Landscape",true,true);
        // 设置字体
        $pdf->SetFont('stsongstdlight', '', 14, '', true);
        $pdf->writeHTML($html);//HTML生成PDF
        //$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $showType= 'F';//PDF输出的方式。I，在浏览器中打开；D，以文件形式下载；F，保存到服务器中；S，以字符串形式输出；E：以邮件的附件输出。
        ob_end_clean();
        $path=public_path('info/');
        //判断保存目录是否存在，不存在则进行创建
        if(!is_dir($path)){
            mkdir($path,'0777',true);
        }
        $pdf->Output($path."{$fileName}.pdf", $showType);
    }
}

