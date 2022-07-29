@extends('backend.common')
@section('body')

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    用户：{$xid} ; 共 {$count} 条记录。
                </div>

                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-12  table-responsive">
                            <table  class="table table-bordered" id="dataTables-example">
                                <colgroup>
                                    <col width="10%">
                                    <col width="10%">
                                    <col width="10%">
                                    <col width="10%">
                                    <col width="10%">
                                    <col width="10%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>序号</th>
                                        <th>钱包</th>
                                        <th>金额</th>
                                        <th>手续费</th>
                                        <th>返利</th>
                                        <th>时间</th>
                                    </tr>
                                </thead>
                                <tbody >
                                    <foreach name="order_list" item="arr" key="key">
                                        <tr>
                                            <td>{$arr.orderId}</td>
                                            <td>
                                                <switch name="arr.walletId" >
                                                    <case value="1001">BTC</case>
                                                    <case value="1002">RPZ</case>
                                                    <case value="1005">LTC</case>
                                                    <case value="1006">BCH</case>
                                                    <default />
                                                </switch>
                                            </td>
                                            <td>{$arr.amount}</td>
                                            <td>{$arr.fee}</td>
                                            <td>{$arr.rebate}</td>
                                            <td>{$arr.sendTime|date='Y-m-d H:i:s',###}</td>
                                            </td>
                                        </tr>
                                    </foreach>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                   <!--  <div class="row">
                        <div class="col-lg-6">
                            当前第{:$nowpage?$nowpage:'1'}页,共{:$totalPages}页,{:$totalRows}条数据
                        </div>
                        <div class="col-lg-6">
                            <ul class="pagination pull-right">
                            {$show}
                            </ul>
                        </div>
                    </div> -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection()
