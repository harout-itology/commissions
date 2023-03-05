<?php

namespace Tests\Feature;

use App\Services\CommissionsService;
use Tests\TestCase;
use Mockery;

class CSVImportTest extends TestCase
{
    public function test_csv_file_import(): void
    {
        $csv = [
            ["2014-12-31",4,"private","withdraw",1200,"EUR"],
            ["2015-01-01",4,"private","withdraw",1000,"EUR"],
            ["2016-01-05",4,"private","withdraw",1000,"EUR"],
            ["2016-01-05",1,"private","deposit",200,"EUR"],
            ["2016-01-06",2,"business","withdraw",300,"EUR"],
            ["2016-01-06",1,"private","withdraw",30000,"JPY"],
            ["2016-01-07",1,"private","withdraw",1000,"EUR"],
            ["2016-01-07",1,"private","withdraw",100,"USD"],
            ["2016-01-10",1,"private","withdraw",100,"EUR"],
            ["2016-01-10",2,"business","deposit",10000,"EUR"],
            ["2016-01-10",3,"private","withdraw",1000,"EUR"],
            ["2016-02-15",1,"private","withdraw",300,"EUR"],
            ["2016-02-19",5,"private","withdraw",3000000,"JPY"]
        ];
        $sheet = collect($csv);

        $mock = Mockery::mock(CommissionsService::class)->makePartial();
        $mock->shouldReceive('exchangeRates')->andReturn(json_decode('{"rates":{"JPY":130.87,"USD":1.13}}'));
        $commissions = $mock->calculations($sheet);

        $this->assertEquals(
            ["0,60", "3,00", "0,00", "0,06", "1,50", "0,00", "0,69", "0,30", "0,30", "3,00", "0,00", "0,00", "8607,39"],
            $commissions->toArray()
        );
    }
}
