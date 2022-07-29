<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>SB Admin 2 - Bootstrap Admin Theme</title>

    <!-- Bootstrap Core CSS -->
    <link href="{{asset('vendor/bootstrap/css/bootstrap.min.css')}}" rel="stylesheet">

    <!-- MetisMenu CSS -->
    <link href="{{asset('vendor/metisMenu/metisMenu.min.css')}}" rel="stylesheet">


    <!-- Custom Fonts -->
    <link href="{{asset('vendor/font-awesome/css/font-awesome.min.css')}}" rel="stylesheet" type="text/css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

</head>

<body>

<div class="container">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Please Sign In</h3>

                    <p>
                        {{--@if(count($errors)>0)--}}
                        {{--@foreach($errors->all() as $error)--}}
                        {{--{{$error}}--}}
                        {{--@endforeach--}}
                        {{--@endif--}}
                    </p>
                </div>
                <div class="panel-body">
                    <form action="" method="post">
                        <input type="hidden" name="_token" value="{{csrf_token()}}">
                        {{--<fieldset>--}}
                        <div class="form-group @if ($errors->has('username')) has-error @endif ">
                            <input class="form-control" placeholder="Username" name="username"  autofocus value="{{old('username')}}" aria-describedby="helpBlock2">
                            @if ($errors->has('username')) <span id="helpBlock2" class="help-block"> {{$errors->first('username')}} </span> @endif
                        </div>

                        <div class="form-group @if ($errors->has('password')) has-error @endif ">
                            <input class="form-control" placeholder="Password" name="password" type="password">
                            @if ($errors->has('password')) <span id="helpBlock2" class="help-block"> {{$errors->first('password')}} </span> @endif
                        </div>

                        <div class="form-group row @if ($errors->has('captcha')) has-error @endif ">
                            <div class="col-md-7">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Captcha " name="captcha">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <img src="{{captcha_src()}}" alt="" class="form-control" style="padding: 0px;cursor: pointer"; onclick="this.src='{{captcha_src()}}'+ Math.random()">
                                </div>
                            </div>
                            @if ($errors->has('captcha')) <span id="helpBlock2" class="help-block"> &nbsp;&nbsp;&nbsp; {{$errors->first('captcha')}} </span> @endif
                        </div>


                        <!-- Change this to a button or input when using this as a form -->
                        <button type="submit" class="btn btn-lg btn-success btn-block">Login</button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="{{asset('vendor/jquery/jquery.min.js')}}"></script>

<!-- Bootstrap Core JavaScript -->
<script src="{{asset('vendor/bootstrap/js/bootstrap.min.js')}}"></script>

<!-- Metis Menu Plugin JavaScript -->
<script src="{{asset('vendor/metisMenu/metisMenu.min.js')}}"></script>


</body>

</html>
