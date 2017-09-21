<?php

namespace shipping;

class correios extends instance
{
    const SEDEX = 40010;
    const SEDEX_A_COBRAR = 40045;
    const SEDEX_10 = 40215;
    const SEDEX_HOJE = 40290;
    const PAC = 41106;

    const ESTIMATE_PRICE = 1;
    const ESTIMATE_TIME = 2;
    const ESTIMATE_BOTH = 3;

    const FORMAT_BOX = 1;
    const FORMAT_PRISM = 2;
    const FORMAT_ENVELOPE = 3;

    const OPTIONAL_IN_PERSON = 1;
    const OPTIONAL_INSURANCE = 2;
    const OPTIONAL_NOTIFICATION = 3;

    protected static $formats = [
        self::FORMAT_BOX,
        self::FORMAT_PRISM,
        self::FORMAT_ENVELOPE,
    ];

    protected static $url = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx';

    protected $result = null;

    protected $estimate = self::ESTIMATE_BOTH;
    protected $origin;
    protected $destination;
    protected $weight;
    protected $format;
    protected $dimensions = [
        'length' => null,
        'width' => null,
        'height' => null,
        'diameter' => null,
    ];
    protected $services = [];
    protected $contract = [
        'code' => null,
        'password' => null,
    ];
    protected $optional = [
        self::OPTIONAL_IN_PERSON => 'N',
        self::OPTIONAL_INSURANCE => 0.00,
        self::OPTIONAL_NOTIFICATION => 'N',
    ];

    public function __construct($origin, $contractCode = null, $contractPassword = null)
    {
        $this->origin = $this::validateCEP($origin);
        if (!$this->origin) {
            throw new \InvalidArgumentException('Please inform a valid origin CEP code');
        }

        $this->contract['code'] = $contractCode;
        $this->contract['password'] = $contractPassword;
    }

    public function estimate($type)
    {
        $this->estimate = (integer) $type;

        return $this;
    }

    public function destination($value)
    {
        $this->destination = $this::validateCEP($value);
        if (!$this->destination) {
            throw new \InvalidArgumentException('Please inform a valid destination CEP code');
        }

        return $this;
    }

    public function service($id)
    {
        $service = (integer) $id;
        if (!$service) {
            throw new \InvalidArgumentException('Please inform an integer service ID');
        }

        if (!in_array($id, $this->services)) {
            $this->services[] = $id;
        }

        return $this;
    }

    public function weight($value)
    {
        $this->weight = (float) $value;
        if (!$this->weight) {
            throw new \InvalidArgumentException('Please inform a valid float weight');
        }

        return $this;
    }

    public function format($code)
    {
        if (!in_array($code, static::$formats)) {
            throw new \InvalidArgumentException('Please inform a valid format');
        }

        $this->format = $code;

        return $this;
    }

    public function dimensions($length, $width, $height = null, $diameter = null)
    {
        foreach (['length', 'width', 'height', 'diameter'] as $field) {
            $$field = (float) $$field;
        }

        if (!$length) {
            throw new \InvalidArgumentException('Please inform a valid length');
        }

        if (!$width) {
            throw new \InvalidArgumentException('Please inform a valid length');
        }

        $this->dimensions['length'] = $length;
        $this->dimensions['width'] = $width;
        $this->dimensions['height'] = $height;
        $this->dimensions['diameter'] = $diameter;

        return $this;
    }

    public function inPerson($enable)
    {
        $this->optional[static::OPTIONAL_IN_PERSON] = $enable ? 'S' : 'N';

        return $this;
    }

    public function insurance($value)
    {
        $this->optional[static::OPTIONAL_INSURANCE] = (float) $value;

        return $this;
    }

    public function notifyDelivery($enable)
    {
        $this->optional[static::OPTIONAL_NOTIFICATION] = $enable ? 'S' : 'N';

        return $this;
    }

    protected function inquire()
    {
        if (empty($this->services)) {
            throw new \BadMethodCallException('There is no service to inquire');
        }

        if (!$this::validateCEP($this->destination)) {
            throw new \BadMethodCallException('There is no destination CEP code to inquire');
        }

        $this->dimensions(...array_values($this->dimensions));

        $data = [
            'nCdEmpresa' => $this->contract['code'],
            'sDsSenha' => $this->contract['password'],
            'nCdServico' => implode(',', $this->services),
            'sCepOrigem' => $this->origin,
            'sCepDestino' => $this->destination,
            'nVlPeso' => $this->weight($this->weight)->weight,
            'nCdFormato' => $this->format($this->format)->format,
            'nVlComprimento' => $this->dimensions['length'],
            'nVlAltura' => $this->dimensions['height'],
            'nVlLargura' => $this->dimensions['width'],
            'nVlDiametro' => $this->dimensions['diameter'],
            'sCdMaoPropria' => $this->optional[static::OPTIONAL_IN_PERSON],
            'nVlValorDeclarado' => $this->optional[static::OPTIONAL_INSURANCE],
            'sCdAvisoRecebimento' => $this->optional[static::OPTIONAL_NOTIFICATION],
            'StrRetorno' => 'XML',
            'nIndicaCalculo' => $this->estimate,
        ];

        $curl = curl_init(static::$url . '?' . http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);

        if (!$response) {
            throw new \UnexpectedValueException('Got no response from API');
        }

        $xml = simplexml_load_string($response);
        if (!$xml || !isset($xml->cServico)) {
            throw new \DOMException('Unable to read response XML');
        }

        $options = [];

        foreach ($xml->cServico as $service) {
            if ((integer) $service->Erro) {
                continue;
            }

            $options[(integer) $service->Codigo] = [
                'time' => isset($service->PrazoEntrega) ? (integer) $service->PrazoEntrega : null,
                'cost' => isset($service->Valor) ? (float) str_replace(',', '.', $service->Valor) : null,
                'raw' => $service,
            ];
        }

        $this->result = $options;
    }

    public function getResult()
    {
        if (is_null($this->result)) {
            $this->inquire();
        }

        return $this->result;
    }
}
