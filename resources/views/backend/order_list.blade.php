@extends('backend.common')
@section('body')

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{--<div class="panel-heading">--}}
                        {{----}}
                    {{--</div>--}}
                    <form action="{{url('backend/order_list')}}" method="post" data-flag="2">
                        <div class="row">
                            {{csrf_field()}}
                            <div class="col-lg-1 col-xs-4"> 选择钱包 </div>
                            <div class="col-lg-3 col-xs-4">
                                <select name="current_id" class="form-control">
                                    @foreach($current_ids as $item)
                                        <option value ="{{ $item }}" @if($current_id == $item)selected=""@endif>{{ $item }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"> 确定 </button>
                            <input type="hidden" name="id" value="{{$id}}">
                        </div>
                    </form>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <h4>&nbsp;&nbsp;&nbsp;&nbsp;用户：{{$id}} ，收入：{{$balance['sql_total_balance']}}；支出：{{$balance['sql_used_balance']}}；余额：{{$balance['sql_usable_balance']}}；</h4>
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
                                        <th>收入</th>
                                        <th>支出</th>
                                        <th>手续费</th>
                                        <th>返利</th>
                                        <th>时间</th>
                                    </tr>
                                </thead>
                                <tbody >
                                    @foreach($order_list as $item)
                                        {{--@foreach($arr as $item)--}}
                                        <tr>
                                            <td>{{ $item['order_sn'] }}</td>
                                            <td>{{ $item['current_id'] }}</td>
                                            <td>@if($item['is_send'] == 2){{ $item['amount'] }}@endif</td>
                                            <td>@if($item['is_send'] == 1){{ $item['amount'] }}@endif</td>
                                            <td>{{ $item['fee'] }}</td>
                                            <td>{{ $item['rebate'] }}</td>
                                            <td>{{ $item['send_time'] }}</td>
                                            </td>
                                        </tr>
                                        {{--@endforeach--}}
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection()
