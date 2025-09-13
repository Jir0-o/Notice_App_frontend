@extends('layouts.guest')
@section('content')
    <section class="vh-100" style="background-color: #eee;">
        <div class="container h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-lg-12 col-xl-11">
                    <div class="card text-black" style="border-radius: 25px;">
                        <div class="card-body p-md-5">
                            <div class="row justify-content-center">
                                <div class="col-md-12 col-lg-12 col-xl-12 order-2 order-lg-1">

                                    <p class="text-center h4 fw-bold">Password Reset</p>
                                    <div class="card-body p-md-5">
                                        <div class="row justify-content-center">
                                            <p class="card-text py-2">
                                                {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
                                            </p>
                                            @if (session('status'))
                                                <div class="mb-4 font-medium text-sm text-green-600">
                                                    {{ session('status') }}
                                                </div>
                                            @endif

                                            <x-validation-errors class="mb-4" />
                                            <form method="POST" action="{{ route('password.email') }}">
                                                @csrf

                                                <div class="block">
                                                    <x-label for="email" value="{{ __('Email') }}" />
                                                    <x-input id="email" class="block mt-1 w-full" type="email"
                                                        name="email" :value="old('email')" required autofocus
                                                        autocomplete="username" />
                                                </div>
                                                <div class="row">
                                                    <div class="col-12 col-md-6">
                                                        <a href="{{ url()->previous() }}" class="mt-4 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Back</a>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <div class="flex items-center justify-end mt-4">
                                                            <x-button>
                                                                {{ __('Email Password Reset Link') }}
                                                            </x-button>
                                                        </div>
                                                    </div>
                                                </div>

                                            </form>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
    </section>
    <div class="container h-100">
        <div class="row d-flex justify-content-center align-items-center h-100">
            <div class="col-lg-12 col-xl-11">
                <div class="card text-black" style="border-radius: 25px;">




                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
@endsection
