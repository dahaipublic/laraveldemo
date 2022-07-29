@extends('backend.common')
@section('body')
        <div id="page-wrapper">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                            </div>
                            <div class="panel-body">
                                <form action="{{url('backend/user_list')}}" method="post" data-flag="2">
                                    <div class="row">
                                        {{csrf_field()}}
                                        <div class="col-lg-1 col-xs-4"> 搜索会员 </div>
                                        <div class="col-lg-3 col-xs-4">
                                            <input type="text" name="search"  class="form-control" placeholder="邮箱/手机号/uid">
                                        </div>
                                        <button type="submit" class="btn btn-primary"> 搜索 </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            用户列表
                        </div>
                        <div class="panel-body">
                            <table width="100%" class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>uid</th>
                                        <th>邮箱</th>
                                        <th>BTC余额</th>
                                        <th>BTC_sql余额</th>
                                        <th>RPZ余额</th>
                                        <th>RPZ_sql余额</th>
                                        <th>LTC余额</th>
                                        <th>LTC_sql余额</th>
                                        <th>ETH余额</th>
                                        <th>ETH_sql余额</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($list as $arr)
                                        <tr class="odd gradeX">
                                            <td>{{$arr['id']}}</td>
                                            <td>{{$arr['email']}}</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td>{{ $arr[$arr['id']][1002]['usable_balance'] }}</td>
                                            <td>{{$arr[$arr['id']][1002]['sql_usable_balance']}}</td>
                                            <td>{{$arr[$arr['id']][1005]['usable_balance']}}</td>
                                            <td>{{$arr[$arr['id']][1005]['sql_usable_balance']}}</td>
                                            <td>{{$arr[$arr['id']][1003]['usable_balance']}}</td>
                                            <td>{{$arr[$arr['id']][1003]['sql_usable_balance']}}</td>
                                            <td>
                                                <a href="{{ url('backend/detail/'.$arr['id']) }}"> 详情 </a> |
                                                <a href="{{ url('backend/order_list/'.$arr['id']).'/1002' }}"> 用户收支详情 </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if($user_list != '')
                        {{ $user_list->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection()