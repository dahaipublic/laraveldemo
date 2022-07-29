<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title> 管理后台 </title>

    <!-- Bootstrap Core CSS -->
    <link href="{{asset('vendor/bootstrap/css/bootstrap.min.css')}}" rel="stylesheet">

    <!-- MetisMenu CSS -->
    <link href="{{asset('vendor/metisMenu/metisMenu.min.css')}}" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="{{asset('css/backend/sb-admin-2.css')}}" rel="stylesheet">

    <!-- Morris Charts CSS -->
    <link href="{{asset('vendor/morrisjs/morris.css')}}" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="{{asset('vendor/font-awesome/css/font-awesome.min.css')}}" rel="stylesheet" type="text/css">

    <link href="https://cdn.bootcss.com/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css" rel="stylesheet">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        .nav-blue{
            background-color:#337ab7;
        }
    </style>
</head>
<body>
<ul class="nav navbar-top-links navbar-right" style="background:white;">
    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
            <i class="fa fa-user fa-fw"></i> <i class="fa fa-caret-down"></i>
        </a>
        <ul class="dropdown-menu dropdown-user">
            <li><a href="{{url('backend/logout')}}"><i class="fa fa-sign-out fa-fw"></i> Logout</a>
            </li>
        </ul>
        <!-- /.dropdown-user -->
    </li>
    <!-- /.dropdown -->
</ul>
<div id="wrapper">
    <!-- Navigation -->
{{--    <nav class="navbar navbar-default navbar-static-top nav-blue" role="navigation" style="margin-bottom: 0">--}}
{{--        <div class="navbar-header">--}}
{{--            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">--}}
{{--                <span class="sr-only">Toggle navigation</span>--}}
{{--                <span class="icon-bar"></span>--}}
{{--                <span class="icon-bar"></span>--}}
{{--                <span class="icon-bar"></span>--}}
{{--            </button>--}}
{{--            <a class="navbar-brand" href="{{url('backend')}}"><span style="color:white;"> 管理后台 </span></a>--}}
{{--        </div>--}}
{{--        <!-- /.navbar-header -->--}}

{{--        <ul class="nav navbar-top-links navbar-right" style="background:white;">--}}
{{--            <li class="dropdown">--}}
{{--                <a class="dropdown-toggle" data-toggle="dropdown" href="#">--}}
{{--                    <i class="fa fa-user fa-fw"></i> <i class="fa fa-caret-down"></i>--}}
{{--                </a>--}}
{{--                <ul class="dropdown-menu dropdown-user">--}}
{{--                    <li><a href="#"><i class="fa fa-user fa-fw"></i> User Profile</a>--}}
{{--                    </li>--}}
{{--                    <li><a href="#"><i class="fa fa-gear fa-fw"></i> Settings</a>--}}
{{--                    </li>--}}
{{--                    <li class="divider"></li>--}}
{{--                    <li><a href="{{url('backend/logout')}}"><i class="fa fa-sign-out fa-fw"></i> Logout</a>--}}
{{--                    </li>--}}
{{--                </ul>--}}
{{--                <!-- /.dropdown-user -->--}}
{{--            </li>--}}
{{--            <!-- /.dropdown -->--}}
{{--        </ul>--}}
{{--        <!-- /.navbar-top-links -->--}}

{{--        <div class="navbar-default sidebar" role="navigation">--}}
{{--            <div class="sidebar-nav navbar-collapse">--}}
{{--                <ul class="nav" id="side-menu">--}}
{{--                    --}}{{--<li class="sidebar-search">--}}
{{--                        --}}{{--<div class="input-group custom-search-form">--}}
{{--                            --}}{{--<input type="text" class="form-control" placeholder="Search...">--}}
{{--                            --}}{{--<span class="input-group-btn">--}}
{{--                                --}}{{--<button class="btn btn-default" type="button">--}}
{{--                                    --}}{{--<i class="fa fa-search"></i>--}}
{{--                                --}}{{--</button>--}}
{{--                            --}}{{--</span>--}}
{{--                        --}}{{--</div>--}}
{{--                        <!-- /input-group -->--}}
{{--                    --}}{{--</li>--}}
{{--                    <li>--}}
{{--                        <a href="{{url('backend')}}"> 首页 </a>--}}
{{--                    </li>--}}
{{--                    <li>--}}
{{--                        <a href="#"> 用户管理 <span class="fa arrow"></span></a>--}}
{{--                        <ul class="nav nav-second-level">--}}
{{--                            <li>--}}
{{--                                <a href="{{url('backend/user_list')}}"> 用户列表 </a>--}}
{{--                            </li>--}}
{{--                            <li>--}}
{{--                                <a href="{{url('backend/business_list')}}"> 商家列表 </a>--}}
{{--                            </li>--}}
{{--                        </ul>--}}
{{--                        <!-- /.nav-second-level -->--}}
{{--                    </li>--}}
{{--                </ul>--}}
{{--            </div>--}}
{{--            <!-- /.sidebar-collapse -->--}}
{{--        </div>--}}
{{--        <!-- /.navbar-static-side -->--}}
{{--    </nav>--}}
@yield('body')


<!-- jQuery -->
    <script src="{{asset('vendor/jquery/jquery.min.js')}}"></script>

    <!-- Bootstrap Core JavaScript -->
    <script src="{{asset('vendor/bootstrap/js/bootstrap.min.js')}}"></script>

    <!-- Metis Menu Plugin JavaScript -->
    <script src="{{asset('vendor/metisMenu/metisMenu.min.js')}}"></script>

    <!-- Morris Charts JavaScript -->
    <script src="{{asset('vendor/raphael/raphael.min.js')}}"></script>
    <script src="{{asset('vendor/morrisjs/morris.min.js')}}"></script>
    <script src="{{asset('js/backend/morris-data.js')}}"></script>

    <!-- Custom Theme JavaScript -->
    <script src="{{asset('js/backend/sb-admin-2.js')}}"></script>
    <!-- 时间选择器前置脚本 -->
    <script src="https://cdn.bootcss.com/moment.js/2.22.1/moment-with-locales.min.js"></script>

    <!-- 时间选择器核心脚本 -->
    <script src="https://cdn.bootcss.com/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js"></script>


</body>

@yield('script')

</html>
