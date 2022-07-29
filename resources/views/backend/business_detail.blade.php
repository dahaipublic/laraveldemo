@extends('backend.common')
@section('body')

<div id="page-wrapper">
    <!-- 放内容 -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    会员详情
                </div>
                <div class="panel-body">
                    <form action="" method="post">
                    <div class="row">
                        <div class="col-lg-2 zz_col_center col-xs-4">id：</div>
                        <div class="col-lg-6 col-xs-8">
                            {{--<input type="text" name="email" class="form-control" value="">--}}
                            {{$seller_info->sellerId}}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-2 zz_col_center col-xs-4">邮箱：</div>
                        <div class="col-lg-6 col-xs-8">
                            {{--<input type="text" name="email" class="form-control" value="">--}}
                            {{$seller_info->email}}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-2 zz_col_center col-xs-4">区号：</div>
                        <div class="col-lg-6 col-xs-8">
                            {{--<input type="text" name="region"  class=" form-control" value="" >--}}
                            {{$seller_info->area}}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-2 zz_col_center col-xs-4">手机：</div>
                        <div class="col-lg-6 col-xs-8">
                            {{--<input type="text" name="phone"  class=" form-control" value="" >--}}
                            {{$seller_info->phone_number}}
                        </div>
                    </div>
                    <div class="row zz_submit">
                        <input type="hidden" name="account"  class=" form-control" value="{{$seller_info->sellerId}}" >
                        <div class="col-lg-6 col-lg-offset-2 col-xs-4 col-xs-offset-4">
                            {{--<button type="submit" class="btn btn-primary">修改</button>--}}
                        </div>
                    </div>
                    </form>
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
                                    <th>钱包</th>
                                    <th>pos_id</th>
                                    <th>total_balance</th>
                                    <th>used_balance</th>
                                    <th>usable_balance</th>
                                    <th></th>
                                    <th>sql_total_balance</th>
                                    <th>sql_used_balance</th>
                                    <th>sql_usable_balance</th>
                                </tr>
                                </thead>
                                <tbody >
                                @foreach($current_info as $item)
                                    <tr>
                                        <td>{{ $item->current_id }}</td>
                                        <td>{{ $item->pos_id }}</td>
                                        <td>{{ $item->total_balance }}</td>
                                        <td>{{ $item->used_balance }}</td>
                                        <td>{{ $item->usable_balance }}</td>
                                        <td></td>
                                        <td>{{ $item->sql_total_balance }}</td>
                                        <td>{{ $item->sql_used_balance }}</td>
                                        <td>{{ $item->sql_usable_balance }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>
@endsection()
