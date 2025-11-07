<?php

namespace LibreNMS\Enum;

use LibreNMS\Traits\EnumToArray;

enum Sensor: string
{
    use EnumToArray;

    case AIRFLOW = 'airflow';
    case BER = 'ber';
    case BITRATE = 'bitrate';
    case CHARGE = 'charge';
    case CHROMATIC_DISPERSION = 'chromatic_dispersion';
    case COOLING = 'cooling';
    case COUNT = 'count';
    case CURRENT = 'current';
    case DBM = 'dbm';
    case DELAY = 'delay';
    case EER = 'eer';
    case FANSPEED = 'fanspeed';
    case FREQUENCY = 'frequency';
    case HUMIDITY = 'humidity';
    case LOAD = 'load';
    case LOSS = 'loss';
    case PERCENT = 'percent';
    case POWER = 'power';
    case POWER_CONSUMED = 'power_consumed';
    case POWER_FACTOR = 'power_factor';
    case PRESSURE = 'pressure';
    case QUALITY_FACTOR = 'quality_factor';
    case RUNTIME = 'runtime';
    case SIGNAL = 'signal';
    case SNR = 'snr';
    case STATE = 'state';
    case TEMPERATURE = 'temperature';
    case TV_SIGNAL = 'tv_signal';
    case VOLTAGE = 'voltage';
    case WATERFLOW = 'waterflow';
    case SIGNAL_LOSS = 'signal_loss';

    public function unit(): string
    {
        return match ($this) {
            self::AIRFLOW => 'cfm',
            self::BER => 'ratio',
            self::BITRATE => 'bps',
            self::CHARGE => '%',
            self::CHROMATIC_DISPERSION => 'ps/nm',
            self::COOLING => 'W',
            self::COUNT => '#',
            self::CURRENT => 'A',
            self::DBM => 'dBm',
            self::DELAY => 's',
            self::EER => 'eer',
            self::FANSPEED => 'rpm',
            self::FREQUENCY => 'Hz',
            self::HUMIDITY => '%',
            self::LOAD => '%',
            self::LOSS => '%',
            self::PERCENT => '%',
            self::POWER => 'W',
            self::POWER_CONSUMED => 'kWh',
            self::POWER_FACTOR => 'ratio',
            self::PRESSURE => 'kPa',
            self::QUALITY_FACTOR => 'dB',
            self::RUNTIME => 'Min',
            self::SIGNAL => 'dBm',
            self::SNR => 'SNR', // TODO: dB?
            self::STATE => '#',
            self::TEMPERATURE => '°C',
            self::TV_SIGNAL => 'dBmV',
            self::VOLTAGE => 'V',
            self::WATERFLOW => 'l/m',
            self::SIGNAL_LOSS => 'dB',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AIRFLOW => 'angle-double-right',
            self::BER => 'sort-amount-desc',
            self::BITRATE => 'bar-chart',
            self::CHARGE => 'battery-half',
            self::CHROMATIC_DISPERSION => 'indent',
            self::COOLING => 'thermometer-full',
            self::COUNT => 'hashtag',
            self::CURRENT => 'bolt fa-flip-horizontal',
            self::DBM => 'sun-o',
            self::DELAY => 'clock-o',
            self::EER => 'snowflake-o',
            self::FANSPEED => 'refresh',
            self::FREQUENCY => 'line-chart',
            self::HUMIDITY => 'tint',
            self::LOAD => 'percent',
            self::LOSS => 'percentage',
            self::PERCENT => 'percent',
            self::POWER => 'power-off',
            self::POWER_CONSUMED => 'plug',
            self::POWER_FACTOR => 'calculator',
            self::PRESSURE => 'thermometer-empty',
            self::QUALITY_FACTOR => 'arrows',
            self::RUNTIME => 'hourglass-half',
            self::SIGNAL => 'wifi',
            self::SNR => 'signal',
            self::STATE => 'bullseye',
            self::TEMPERATURE => 'thermometer-three-quarters',
            self::TV_SIGNAL => 'signal',
            self::VOLTAGE => 'bolt',
            self::WATERFLOW => 'tint',
            self::SIGNAL_LOSS => 'wave-square'
        };
    }

    /**
     * @return class-string
     */
    public function discoveryInterface(): string
    {
        return match ($this) {
            self::AIRFLOW => \LibreNMS\Interfaces\Discovery\Sensors\SensorAirflowDiscovery::class,
            self::BER => \LibreNMS\Interfaces\Discovery\Sensors\SensorBerDiscovery::class,
            self::BITRATE => \LibreNMS\Interfaces\Discovery\Sensors\SensorBitRateDiscovery::class,
            self::CHARGE => \LibreNMS\Interfaces\Discovery\Sensors\SensorChargeDiscovery::class,
            self::CHROMATIC_DISPERSION => \LibreNMS\Interfaces\Discovery\Sensors\SensorChromaticDispersionDiscovery::class,
            self::COOLING => \LibreNMS\Interfaces\Discovery\Sensors\SensorCoolingDiscovery::class,
            self::COUNT => \LibreNMS\Interfaces\Discovery\Sensors\SensorCountDiscovery::class,
            self::CURRENT => \LibreNMS\Interfaces\Discovery\Sensors\SensorCurrentDiscovery::class,
            self::DBM => \LibreNMS\Interfaces\Discovery\Sensors\SensorDbmDiscovery::class,
            self::DELAY => \LibreNMS\Interfaces\Discovery\Sensors\SensorDelayDiscovery::class,
            self::EER => \LibreNMS\Interfaces\Discovery\Sensors\SensorEerDiscovery::class,
            self::FANSPEED => \LibreNMS\Interfaces\Discovery\Sensors\SensorFanspeedDiscovery::class,
            self::FREQUENCY => \LibreNMS\Interfaces\Discovery\Sensors\SensorFrequencyDiscovery::class,
            self::HUMIDITY => \LibreNMS\Interfaces\Discovery\Sensors\SensorHumidityDiscovery::class,
            self::LOAD => \LibreNMS\Interfaces\Discovery\Sensors\SensorLoadDiscovery::class,
            self::LOSS => \LibreNMS\Interfaces\Discovery\Sensors\SensorLossDiscovery::class,
            self::PERCENT => \LibreNMS\Interfaces\Discovery\Sensors\SensorPercentDiscovery::class,
            self::POWER => \LibreNMS\Interfaces\Discovery\Sensors\SensorPowerDiscovery::class,
            self::POWER_CONSUMED => \LibreNMS\Interfaces\Discovery\Sensors\SensorPowerConsumedDiscovery::class,
            self::POWER_FACTOR => \LibreNMS\Interfaces\Discovery\Sensors\SensorPowerFactorDiscovery::class,
            self::PRESSURE => \LibreNMS\Interfaces\Discovery\Sensors\SensorPressureDiscovery::class,
            self::QUALITY_FACTOR => \LibreNMS\Interfaces\Discovery\Sensors\SensorQualityFactorDiscovery::class,
            self::RUNTIME => \LibreNMS\Interfaces\Discovery\Sensors\SensorRuntimeDiscovery::class,
            self::SIGNAL => \LibreNMS\Interfaces\Discovery\Sensors\SensorSignalDiscovery::class,
            self::SNR => \LibreNMS\Interfaces\Discovery\Sensors\SensorSnrDiscovery::class,
            self::STATE => \LibreNMS\Interfaces\Discovery\Sensors\SensorStateDiscovery::class,
            self::TEMPERATURE => \LibreNMS\Interfaces\Discovery\Sensors\SensorTemperatureDiscovery::class,
            self::TV_SIGNAL => \LibreNMS\Interfaces\Discovery\Sensors\SensorTvSignalDiscovery::class,
            self::VOLTAGE => \LibreNMS\Interfaces\Discovery\Sensors\SensorVoltageDiscovery::class,
            self::WATERFLOW => \LibreNMS\Interfaces\Discovery\Sensors\SensorWaterflowDiscovery::class,
            self::SIGNAL_LOSS => \LibreNMS\Interfaces\Discovery\Sensors\SensorSignalLossDiscovery::class,
        };
    }

    /**
     * @return class-string
     */
    public function getPollingInterface(): string
    {
        return match($this) {
            self::AIRFLOW => \LibreNMS\Interfaces\Polling\Sensors\SensorAirflowPolling::class,
            self::BER => \LibreNMS\Interfaces\Polling\Sensors\SensorBerPolling::class,
            self::BITRATE => \LibreNMS\Interfaces\Polling\Sensors\SensorBitratePolling::class,
            self::CHARGE => \LibreNMS\Interfaces\Polling\Sensors\SensorChargePolling::class,
            self::CHROMATIC_DISPERSION => \LibreNMS\Interfaces\Polling\Sensors\SensorChromaticDispersionPolling::class,
            self::COOLING => \LibreNMS\Interfaces\Polling\Sensors\SensorCoolingPolling::class,
            self::COUNT => \LibreNMS\Interfaces\Polling\Sensors\SensorCountPolling::class,
            self::CURRENT => \LibreNMS\Interfaces\Polling\Sensors\SensorCurrentPolling::class,
            self::DBM => \LibreNMS\Interfaces\Polling\Sensors\SensorDbmPolling::class,
            self::DELAY => \LibreNMS\Interfaces\Polling\Sensors\SensorDelayPolling::class,
            self::EER => \LibreNMS\Interfaces\Polling\Sensors\SensorEerPolling::class,
            self::FANSPEED => \LibreNMS\Interfaces\Polling\Sensors\SensorFanspeedPolling::class,
            self::FREQUENCY => \LibreNMS\Interfaces\Polling\Sensors\SensorFrequencyPolling::class,
            self::HUMIDITY => \LibreNMS\Interfaces\Polling\Sensors\SensorHumidityPolling::class,
            self::LOAD => \LibreNMS\Interfaces\Polling\Sensors\SensorLoadPolling::class,
            self::LOSS => \LibreNMS\Interfaces\Polling\Sensors\SensorLossPolling::class,
            self::PERCENT => \LibreNMS\Interfaces\Polling\Sensors\SensorPercentPolling::class,
            self::POWER => \LibreNMS\Interfaces\Polling\Sensors\SensorPowerPolling::class,
            self::POWER_CONSUMED => \LibreNMS\Interfaces\Polling\Sensors\SensorPowerConsumedPolling::class,
            self::POWER_FACTOR => \LibreNMS\Interfaces\Polling\Sensors\SensorPowerFactorPolling::class,
            self::PRESSURE => \LibreNMS\Interfaces\Polling\Sensors\SensorPressurePolling::class,
            self::QUALITY_FACTOR => \LibreNMS\Interfaces\Polling\Sensors\SensorQualityFactorPolling::class,
            self::RUNTIME => \LibreNMS\Interfaces\Polling\Sensors\SensorRuntimePolling::class,
            self::SIGNAL => \LibreNMS\Interfaces\Polling\Sensors\SensorSignalPolling::class,
            self::SNR => \LibreNMS\Interfaces\Polling\Sensors\SensorSnrPolling::class,
            self::STATE => \LibreNMS\Interfaces\Polling\Sensors\SensorStatePolling::class,
            self::TEMPERATURE => \LibreNMS\Interfaces\Polling\Sensors\SensorTemperaturePolling::class,
            self::TV_SIGNAL => \LibreNMS\Interfaces\Polling\Sensors\SensorTvSignalPolling::class,
            self::VOLTAGE => \LibreNMS\Interfaces\Polling\Sensors\SensorVoltagePolling::class,
            self::WATERFLOW => \LibreNMS\Interfaces\Polling\Sensors\SensorWaterflowPolling::class,
            self::SIGNAL_LOSS => \LibreNMS\Interfaces\Polling\Sensors\SensorSignalLossPolling::class,
        };
    }
}
