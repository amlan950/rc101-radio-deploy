@extends('layouts.app')

@section('title', 'Listen Live')

@section('content')
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="brand-font">🎵 Listen Live</h1>
                    <p>Tune in to our live radio stream 24/7</p>
                    
                    <!-- Live Radio Player -->
                    <div class="text-center my-4">
                        <audio id="live-audio" controls autoplay style="width: 100%; max-width: 500px;">
                            <source src="{{ env('Live_Radio_Player', 'https://streams.radiomast.io/rc101') }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
