<?php

namespace Youshido\GraphQLBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class GraphQLConfigureCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('graphql:configure')
            ->setDescription('Generates GraphQL Schema class')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'Bundle to generate class to', 'AppBundle');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName = $input->getArgument('bundle');
        if (substr($bundleName, -6) != 'Bundle') $bundleName .= 'Bundle';

        $srcPath       = realpath($this->getContainer()->getParameter('kernel.root_dir') . '/../src');
        $activeBundles = $this->getContainer()->getParameter('kernel.bundles');
        if (!array_key_exists($bundleName, $activeBundles)) {
            $output->writeln('There is no active bundleName: ' . $bundleName);
        }

        $className          = 'Schema';
        $namespaceClassName = $bundleName . '/GraphQL/' . $className;
        $graphqlPath        = $srcPath . '/' . $bundleName . '/GraphQL/';
        $classPath          = $graphqlPath . '/' . $className . '.php';

        $inputHelper = $this->getHelper('question');
        if (file_exists($classPath)) {
            $output->writeln(sprintf('Schema class %s was found.', $namespaceClassName));
        } else {
            $question = new ConfirmationQuestion(sprintf('Confirm creating class at %s ? [Y/n]', $namespaceClassName), true);
            if (!$inputHelper->ask($input, $output, $question)) {
                return;
            }

            if (!is_dir($graphqlPath)) {
                mkdir($graphqlPath, 0777, true);
            }
            file_put_contents($classPath, $this->getSchemaClassTemplate($bundleName, $className));

            $output->writeln('Schema file has been created at');
            $output->writeln($classPath . "\n");
        }
        if (!$this->graphQLRouteExists()) {
            $question = new ConfirmationQuestion('Confirm adding GraphQL route? [Y/n]', true);
            $resource = $this->getMainRouteConfig();
            if (!$resource || !$inputHelper->ask($input, $output, $question)) {
                $output->writeln('Update your app/config/config.yml with the parameter:');
                $output->writeln('graph_ql:');
                $output->writeln(sprintf('  schema_class: %s\GraphQL\%s', $bundleName, $className));
            } else {
                $routeConfigData = <<<CONFIG

graphql:
    resource: "@GraphQLBundle/Controller/"
CONFIG;
                file_put_contents($resource, $routeConfigData, FILE_APPEND);
                $output->writeln('Config was added to ' . $resource);
            }
        } else {
            $output->writeln('GraphQL default route was found.');

        }
    }

    protected function getMainRouteConfig()
    {
        $routerResources = $this->getContainer()->get('router')->getRouteCollection()->getResources();
        foreach ($routerResources as $resource) {
            /** @var FileResource|DirectoryResource $resource */
            if (substr($resource->getResource(), -11) == 'routing.yml') {
                return $resource->getResource();
            }
        }

        return null;
    }

    protected function graphQLRouteExists()
    {
        $routerResources = $this->getContainer()->get('router')->getRouteCollection()->getResources();
        foreach ($routerResources as $resource) {
            /** @var FileResource|DirectoryResource $resource */
            if (strpos($resource->getResource(), 'GraphQLController.php') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function generateRoutes()
    {

    }

    protected function getSchemaClassTemplate($bundleName, $className = 'Schema')
    {
        $tpl = <<<TEXT
<?php
/**
 * This class was automatically generated by GraphQL Schema generator
 */

namespace $bundleName\GraphQL;

use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Type\Scalar\StringType;

class $className extends AbstractSchema
{
    public function build(SchemaConfig \$config)
    {
        \$config->getQuery()->addFields([
            'hello' => [
                'type'    => new StringType(),
                'resolve' => function () {
                    return 'world!';
                }
            ]
        ]);
    }

}

TEXT;

        return $tpl;
    }

}
