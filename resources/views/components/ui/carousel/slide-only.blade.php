

@php
    $carouselData = [
        ['thumbnail' => '/images/carousel/carousel-01.png'],
        ['thumbnail' => '/images/carousel/carousel-02.png'],
        ['thumbnail' => '/images/carousel/carousel-03.png'],
        ['thumbnail' => '/images/carousel/carousel-04.png'],
    ];
@endphp

<div class="border border-gray-200 rounded-lg carouselOne dark:border-gray-800">
  <swiper-container slides-per-view="1" autoplay-delay="3000" autoplay-disable-on-interaction="false" loop="true">
    @foreach ($carouselData as $item)
    <swiper-slide>
      <div class="overflow-hidden rounded-lg">
        <img src="{{ $item['thumbnail'] }}" class="rounded-lg" alt="carousel" />
      </div>
    </swiper-slide>
    @endforeach
  </swiper-container>
</div>
