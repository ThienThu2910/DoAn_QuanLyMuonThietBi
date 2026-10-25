<?php /* Minh hoạ hộp thiết bị dạng isometric cho phần giới thiệu — dựng từ SVG mẫu trong tài liệu, đổi sang tông xanh thương hiệu + chuyển động nhẹ */ ?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200" role="img" aria-label="Minh hoạ thiết bị phòng Lab">
    <defs>
        <linearGradient id="ill-g1" x1="0%" y1="0%" x2="10%" y2="100%">
            <stop offset="20%" style="stop-color: var(--ill-grad); stop-opacity: 1"></stop>
            <stop offset="60%" style="stop-color: var(--ill-body); stop-opacity: 1"></stop>
        </linearGradient>
        <linearGradient id="ill-g2" x1="10%" y1="-17%" x2="0%" y2="100%">
            <stop offset="20%" style="stop-color: var(--ill-acc); stop-opacity: 0"></stop>
            <stop offset="100%" style="stop-color: var(--ill-acc); stop-opacity: .33"></stop>
        </linearGradient>
        <linearGradient id="ill-g3" x1="0%" y1="0%" x2="10%" y2="100%">
            <stop offset="20%" style="stop-color: var(--ill-acc); stop-opacity: 0"></stop>
            <stop offset="100%" style="stop-color: var(--ill-acc); stop-opacity: .33"></stop>
        </linearGradient>
    </defs>
    <g>
        <g transform="rotate(45 100 100)"><polygon class="ill-pulse" stroke-width="1" style="stroke: var(--ill-acc)" fill="none" points="70,70 148,50 130,130 50,150"></polygon></g>
        <g transform="rotate(45 100 100)"><polygon class="ill-pulse b" stroke-width="1" style="stroke: var(--ill-acc)" fill="none" points="70,70 148,50 130,130 50,150"></polygon></g>
        <polygon transform="rotate(45 100 100)" style="fill: var(--ill-body)" points="70,70 150,50 130,130 50,150"></polygon>
        <polygon fill="url(#ill-g1)" points="100,70 150,100 100,130 50,100"></polygon>
        <polygon transform="translate(20, 31)" style="fill: var(--ill-acc-2)" points="80,50 80,75 80,99 40,75"></polygon>
        <polygon transform="translate(20, 31)" fill="url(#ill-g2)" points="40,-40 80,-40 80,99 40,75"></polygon>
        <polygon transform="rotate(180 100 100) translate(20, 20)" style="fill: var(--ill-acc)" points="80,50 80,75 80,99 40,75"></polygon>
        <polygon transform="rotate(0 100 100) translate(60, 20)" fill="url(#ill-g3)" points="40,-40 80,-40 80,85 40,110.2"></polygon>
        <polygon class="ill-spark" transform="rotate(45 100 100) translate(80, 95)" style="fill: var(--ill-spark)" points="5,0 5,5 0,5 0,0"></polygon>
        <polygon class="ill-spark b" transform="rotate(45 100 100) translate(80, 55)" style="fill: var(--ill-spark-2)" points="6,0 6,6 0,6 0,0"></polygon>
        <polygon class="ill-spark c" transform="rotate(45 100 100) translate(70, 80)" fill="#fff" points="2,0 2,2 0,2 0,0"></polygon>
        <polygon style="fill: var(--ill-base)" points="29.5,99.8 100,142 100,172 29.5,130"></polygon>
        <polygon transform="translate(50, 92)" style="fill: var(--ill-base-2)" points="50,50 120.5,8 120.5,35 50,80"></polygon>
    </g>
</svg>
