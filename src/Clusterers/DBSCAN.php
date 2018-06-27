<?php

namespace Rubix\ML\Clusterers;

use Rubix\ML\Persistable;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Kernels\Distance\Distance;
use Rubix\ML\Kernels\Distance\Euclidean;
use InvalidArgumentException;

class DBSCAN implements Clusterer, Persistable
{
    const NOISE = -1;

    /**
     * The maximum distance between two points to be considered neighbors. The
     * smaller the value, the tighter the clusters will be.
     *
     * @var float
     */
    protected $radius;

    /**
     * The minimum number of points to from a dense region or cluster.
     *
     * @var int
     */
    protected $minDensity;

    /**
     * The distance function to use when computing the distances between points.
     *
     * @var \Rubix\ML\Contracts\Distance
     */
    protected $kernel;

    /**
     * @param  float  $radius
     * @param  int  $minDensity
     * @param  \Rubix\ML\Contracts\Distance  $kernel
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(float $radius = 0.5, int $minDensity = 5, Distance $kernel = null)
    {
        if ($radius < 0.0) {
            throw new InvalidArgumentException('Epsilon cannot be less than 0.');
        }

        if ($minDensity < 0) {
            throw new InvalidArgumentException('Minimum density must be a'
                . ' number greater than 0.');
        }

        if (!isset($kernel)) {
            $kernel = new Euclidean();
        }

        $this->radius = $radius;
        $this->minDensity = $minDensity;
        $this->kernel = $kernel;
    }

    /**
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @return array
     */
    public function train(Dataset $dataset) : void
    {
        if (in_array(self::CATEGORICAL, $dataset->columnTypes())) {
            throw new InvalidArgumentException('This estimator only works with'
                . ' continuous features.');
        }
    }

    /**
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        $labels = [];
        $current = 0;

        foreach ($dataset as $index => $sample) {
            if (isset($labels[$index])) {
                continue 1;
            }

            $neighbors = $this->groupNeighborsByDistance($sample, $dataset);

            if (count($neighbors) < $this->minDensity) {
                $labels[$index] = self::NOISE;

                continue 1;
            }

            $labels[$index] = $current;

            $this->expand($dataset, $neighbors, $labels, $current);

            $current++;
        }

        return $labels;
    }

    /**
     * Expand the cluster by computing the distance between a sample and each
     * member of the cluster.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @param  array  $neighbors
     * @param  array  $labels
     * @param  int  $current
     * @return void
     */
    protected function expand(Dataset $dataset, array $neighbors, array &$labels, int $current) : void
    {
        while (!empty($neighbors)) {
            $index = array_pop($neighbors);

            if (isset($labels[$index])) {
                if ($labels[$index] === self::NOISE) {
                    $labels[$index] = $current;
                }

                continue 1;
            }

            $labels[$index] = $current;

            $seeds = $this->groupNeighborsByDistance($dataset->row($index),
                $dataset);

            if (count($seeds) >= $this->minDensity) {
                $neighbors = array_unique(array_merge($neighbors, $seeds));
            }
        }
    }

    /**
     * Group the samples into a region defined by their distance from a given
     * centroid.
     *
     * @param  array  $neighbor
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return array
     */
    protected function groupNeighborsByDistance(array $neighbor, Dataset $dataset) : array
    {
        $neighbors = [];

        foreach ($dataset as $index => $sample) {
            $distance = $this->kernel->compute($neighbor, $sample);

            if ($distance <= $this->radius) {
                $neighbors[] = $index;
            }
        }

        return $neighbors;
    }
}