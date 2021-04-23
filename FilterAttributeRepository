<?php

namespace App\Repositories\Genesis\FilterAttribute;

use App\Models\FilterAttribute;
use App\Repositories\Genesis\BaseRepository;

class Repository extends BaseRepository
{
	/**
	 * @var array
	 */
	protected $defaultParams;

	public function __construct(array $attributes = [])
	{
		parent::__construct($attributes);

		$this->defaultParams = [];
	}

	/**
	 * @return string
	 */
	protected function model(): string
	{
		return FilterAttribute::class;
	}

	/**
	 * @return string
	 */
	protected function endpoint(): string
	{
		return 'filter-attributes';
	}

	protected function parameters(): array
	{
		return array_merge(parent::parameters(), $this->defaultParams);
	}
}
