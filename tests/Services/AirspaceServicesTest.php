<?php
/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

use DSNA\NMB2BDriver\Services\AirspaceServices;
use DSNA\NMB2BDriver\Models\EAUPRSAs;

use PHPUnit\Framework\TestCase;

/**
 * Class AirspaceServicesTest
 */
class AirspaceServicesTest extends TestCase
{
    private $airspaceServices;

    private $version;

    private $aixmNS;

    private function getSoapClient() : AirspaceServices
    {
        if($this->airspaceServices == null) {
            $config = include('./tests/config.php');
            $options = array(
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE
            );
            $options['stream_context'] = stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            ));
            $options['local_cert'] = $config['certPath'];
            $options['passphrase'] = $config['passphrase'];
            //$options['proxy_host'] = $config['proxyhost'];
            //$options['proxy_port'] = $config['proxyport'];
            $this->version = $config['version'];
            $this->airspaceServices = new AirspaceServices($config['wsdl']['airspaceServices'], $options, false);

            if($this->getSoapClient()->getNMVersionFloat() >= 23) {
                $this->aixmNS = 'http://www.aixm.aero/schema/5.1.1';
            } else {
                $this->aixmNS = 'http://www.aixm.aero/schema/5.1';
            }
        }
        return $this->airspaceServices;
    }

    public function testRetrieveEAUPChain()
    {
        $chainDate = new \DateTime('2019-12-02');

        $result = $this->getSoapClient()->retrieveEAUPChain($chainDate);

        $this->assertEquals(1, $result->getAUPSequenceNumber());
        $this->assertEquals(9, intval($result->getLastSequenceNumber()));
    }

    /**
     * @covers \DSNA\NMB2BDriver\Services\Service::extractNMVersion()
     * @covers \DSNA\NMB2BDriver\Services\Service::getNMVersion()
     */
    public function testGetNMVersion()
    {
        $nmVersion = $this->getSoapClient()->getNMVersion();
        $this->assertEquals($this->version, $nmVersion);
    }

    public function testRetrieveEAUPRSAs()
    {
        $date = new \DateTime('2019-12-02');
        $designators = "LF*";
        $sequenceNumber = 3;

        $result = $this->getSoapClient()->retrieveEAUPRSAs($designators, $date, $sequenceNumber);

        $lfcba16bXML = $result->getAirspacesWithDesignatorAsXML("LFCBA16B");
        $this->assertEquals(7, count($lfcba16bXML));

        $lfcba16b = $result->getAirspacesWithDesignator("LFCBA16B");
        $this->assertEquals(7, count($lfcba16b));

        $this->assertInstanceOf(\DSNA\NMB2BDriver\Models\Airspace::class, $lfcba16b[0]);

        $airspace = new \DSNA\NMB2BDriver\Models\Airspace($lfcba16bXML[0], $this->aixmNS);

        return $airspace;

    }

    /**
     * @depends testRetrieveEAUPRSAs
     * @param \DSNA\NMB2BDriver\Models\Airspace $airspace
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getDesignator()
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getTimeBegin()
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getDateTimeBegin()
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getTimeEnd()
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getDateTimeEnd()
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getLowerLimit()
     * @covers \DSNA\NMB2BDriver\Models\Airspace::getUpperLimit()
     */
    public function testAirspace(\DSNA\NMB2BDriver\Models\Airspace $airspace)
    {
        $this->assertEquals("LFCBA16B", $airspace->getDesignator());

        $start = "2019-12-02T08:00:00";
        $startDate = new DateTime($start . '+00:00');
        $end = "2019-12-02T20:30:00";
        $endDate = new DateTime($end . '+00:00');

        $this->assertEquals($start, $airspace->getTimeBegin());
        $this->assertEquals($startDate, $airspace->getDateTimeBegin());
        $this->assertEquals($end, $airspace->getTimeEnd());
        $this->assertEquals($endDate, $airspace->getDateTimeEnd());
        $this->assertEquals("65", $airspace->getLowerLimit());
        $this->assertEquals("105", $airspace->getUpperLimit());
    }
}