<?php

namespace App\Repositories\Genesis;

use App\Services\Decorators\ComponentInterface;
use App\Services\Decorators\EloquentModelDecorator;
use App\Services\Genesis\Exceptions\ApiException;
use ContentComponents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use Kaxmedia\LaravelServices\Genesis\GenesisClient;

const MAX_PAGE_SIZE = 100;

abstract class BaseRepository
{
	/**
	 * @var string $endpoint
	 */
	protected $endpoint;

	/**
	 * @var GenesisClient $genesis
	 */
	protected $genesis;

	/**
	 * @var Model $model
	 */
	protected $model;

	/**
	 * Content primary key column name
	 * @var string $cpk
	 */
	protected $cpk = 'cpk';

	/**
	 * Stores parameters being passed to the API endpoint
	 * @var array $parameters
	 */
	protected $parameters = [];

	protected $response;

	protected $decorator;

	protected const MAX_PAGE_SIZE = MAX_PAGE_SIZE;

	/**
	 * BaseRepository constructor.
	 * @param array $attributes
	 */
	public function __construct(array $attributes = [])
	{
		$this->genesis = App::make(GenesisClient::class);
		$this->model = $this->makeModel($attributes);
		$this->endpoint = $this->endpoint();

		if (!$this->model instanceof Model) {
			throw new InvalidArgumentException('foo');
		}

		if ($this->endpoint === null || empty($this->endpoint)) {
			throw new InvalidArgumentException('bar');
		}
	}

	abstract protected function model(): string;

	abstract protected function endpoint(): string;

	protected function makeModel(array $attributes = []): Model
	{
		/**
		 * @var Model $model
		 */
		$model = App::make($this->model(), ['attributes' => $attributes]);
		$model->syncOriginal();

		return $model;
	}

	public function withParameters(array $parameters): BaseRepository
	{
		$this->parameters = array_merge($this->parameters(), $this->parameters, $parameters);

		return $this;
	}

	public function getParameters()
	{
		return $this->parameters;
	}

	protected function bindParameters($parameters): array
	{
		$this->withParameters($parameters);

		// Allow for no locale
		if (empty($this->parameters['locale'])) {
			unset($this->parameters['locale']);
		}

		// Allow for no site_id
		if (empty($this->parameters['site_id'])) {
			unset($this->parameters['site_id']);
		}

		// Allow for no state
		if (empty($this->parameters['state'])) {
			unset($this->parameters['state']);
		}

		return $this->parameters;
	}

	protected function parameters(): array
	{
		return [
			'locale' => 'US',
			'state' => localConfig('state_code'),
			'site_id' => localConfig('genesis_site_id'),
		];
	}

	/**
	 * @param $response
	 * @return Collection|null
	 */
	public function getResponse($response): ?Collection
	{
		$this->response = $response;

		if (empty($response->data)) {
			return null;
		}

		return collect($response->data)->map(function ($item, $key) {
			$model = $this->makeModel((array) $item);

			if ($this->decorator) {
				/**
				 * @var ComponentInterface $decorator
				 */
				$decorator = $this->makeDecorator($model);

				return $decorator->decorate();
			}

			return $model;
		});
	}

	protected function makeDecorator($model)
	{
		$concreteDecorator = App::make(EloquentModelDecorator::class, ['model' => $model]);

		return App::make($this->decorator, ['component' => $concreteDecorator]);
	}

	public function getResponseMeta()
	{
		if (empty($this->response)) {
			return null;
		}

		return (object) array_merge((array) $this->response->meta, (array) $this->response->links);
	}

	/**
	 * @param $response
	 * @return Model|null
	 */
	protected function getFirstResponse($response): ?Model
	{
		if ($response instanceof Collection) {
			return $response->first();
		}

		return null;
	}

	/**
	 * @param string $class
	 * @return BaseRepository
	 */
	public function loadDecorator(string $class): BaseRepository
	{
		if (class_exists($class)) {
			$this->decorator = $class;
		}

		return $this;
	}

	/**
	 * @return Model
	 */
	public function getModel(): Model
	{
		return $this->model;
	}

	/**
	 * Get all data from the api endpoint
	 *
	 * @param array $params
	 * @return Collection|null
	 */
	public function all(array $params = []): ?Collection
	{
		$parameters = $this->bindParameters(array_merge($params, ['page_size' => self::MAX_PAGE_SIZE]));
		$response = $this->get($parameters);

		if ($response === null) {
			return null;
		}

		$meta = $this->getResponseMeta();

		$collection = collect([]);
		$collection->push($response);

		for ($page = 2; $page <= $meta->last_page; $page++) {
			$response = $this->get(array_merge($parameters, ['page' => $page]));
			if ($response === null) {
				continue;
			}
			$collection->push($response);
		}

		return $collection->flatten();
	}

	public function get(array $params = []): Collection
	{
		$response = $this->genesis->get($this->endpoint, $this->bindParameters($params));
		$this->parameters = [];

		return $this->getResponse($response) ?? collect([]);
	}

	public function getAllResultsWithoutDefaultParameters(array $params = []): Collection
	{
		$response = $this->genesis->get($this->endpoint, array_diff_assoc($this->parameters, $this->parameters($params)));
		$this->parameters = [];

		return $this->getResponse($response) ?? collect([]);
	}

	public function first(array $params = []): ?Model
	{
		return ContentComponents::renderFromData($this->getFirstResponse($this->get($params)));
	}

	/**
	 * @param int $contentPrimaryKey
	 * @return Model|null
	 */
	public function getByContentPrimaryKey(int $contentPrimaryKey): ?Model
	{
		return $this->first([$this->cpk => $contentPrimaryKey]);
	}

	/**
	 * @param int $id
	 * @return Model
	 * @throws ApiException
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getById(int $id): Model
	{
		$response = $this->genesis->get($this->endpoint . '/' . $id);

		return ContentComponents::renderFromData($this->makeModel((array) $response->data));
	}
}
