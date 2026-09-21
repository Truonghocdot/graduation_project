<?php

namespace App\Enums;

enum PayerType: string
{
    case Orderer = 'ORDERER';
    case Recipient = 'RECIPIENT';
}
