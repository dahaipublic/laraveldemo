@extends('backend.common')
@section('body')
        <div id="page-wrapper" style="min-height: 418px; width: 100%;margin-left: 1%;">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                            </div>
                            <div class="panel-body">
                                <form action="{{url('backend/error_list')}}" method="get" data-flag="2" class="form-inline">
                                    <div class="form-group">
                                        <label for="start_date">日期：</label>
                                        <input type="text" value="{{request()->input('start_date', "")}}" name="start_date" class="form-control" id="start_date" placeholder="开始日期"> -
                                        <input type="text" value="{{request()->input('end_date', "")}}" name="end_date" class="form-control" id="end_date" placeholder="结束日期">
                                    </div>
                                    <div class="form-group">
                                        <label for="error_info">&nbsp;&nbsp;&nbsp;错误信息：</label>
                                        <input type="text" value="{{request()->input('error_info', "")}}" name="error_info" class="form-control" id="error_info" placeholder="错误信息">
                                    </div>
                                    <div class="form-group">
                                        <label for="path">&nbsp;&nbsp;&nbsp;接口地址：</label>
                                        <input type="text" value="{{request()->input('path', "")}}" name="path" class="form-control" id="path" placeholder="接口地址">
                                    </div>
                                    <div class="form-group">
                                        <label for="OS">&nbsp;&nbsp;&nbsp;OS：</label>
                                        <select class="form-control" id="OS" name="os">
                                            <option value=""  @if(request()->input('os', "") == '') selected @endif>全部</option>
                                            <option value="ios" @if(request()->input('os', "") == 'ios') selected @endif>ios</option>
                                            <option value="android" @if(request()->input('os', "") == 'android') selected @endif>android</option>
                                            <option value="other" @if(request()->input('os', "") == 'other') selected @endif>other</option>
                                        </select>
                                    </div>
                                    &nbsp;&nbsp;&nbsp;
                                        <button type="submit" class="btn btn-primary"> 搜索 </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div >
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                接口错误信息列表
                            </div>
                            <div class="panel-body ">
                                <table class="table table-striped table-bordered table-hover" id="dataTables-example" style="table-layout:fixed;word-wrap: break-word">
                                    <thead>
                                        <tr>
                                            <th width="4%">ID</th>
                                            <th width="4%">OS</th>
                                            <th width="17%">错误信息</th>
                                            <th width="20%">接口地址</th>
                                            <th width="25%">请求参数</th>
                                            <th width="25%">请求头（Header）</th>
                                            <th width="5%">时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($list as $arr)
                                            <tr class="odd gradeX">
                                                <td>{{$arr['id']}}</td>
                                                <td>{{$arr['os']}}</td>
                                                <td>{{$arr['error']}}</td>
                                                <td>{{$arr['route']}}</td>
                                                <td>{{$arr['param']}}</td>
                                                <td>{{$arr['header']}}</td>
                                                <td>{{$arr['created_at']}}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if($error_list != '')
                            {{ $error_list->appends(request()->all())->links() }}
                        @endif
                    </div>
                </div>
        </div>
@endsection()

@section('script')
    <script type="text/javascript">
        $(function () {
            $('#start_date,#end_date').datetimepicker({
                format: 'YYYY-MM-DD HH:mm:ss'
            });
        });

    </script>
@endsection