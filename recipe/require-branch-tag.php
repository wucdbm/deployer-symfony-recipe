<?php

namespace Deployer;

use Deployer\Host\Host;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

option('symfony_env', 'e', InputOption::VALUE_OPTIONAL, 'Environment to run commands in');
option('deploy-ask', 'a', InputOption::VALUE_OPTIONAL, 'Ask what to deploy');

$start = new \DateTime();

// Display Time Elapsed once done
task('deploy:time_elapsed', function () use ($start) {
    $end = new \DateTime();
    $diff = $end->diff($start);
    output()->writeln(sprintf('<info>Total Time Elapsed: %sm %ss</info>', $diff->i, $diff->s));
});

after('deploy', 'deploy:time_elapsed');

// Display a System Notification if Slack is not enabled
task('notification:system', function () use ($start) {
    if (has('slack_webhook') && get('slack_webhook')) {
        // Do not display the system notification if slack web hook is configured
        return;
    }

    if (has('slack_skip_notification') && !get('slack_skip_notification')) {
        // Do not display the system notification if slack notification is configured
        return;
    }

    if (has('disable_system_notification') && get('disable_system_notification')) {
        return;
    }

    $end = new \DateTime();
    $diff = $end->diff($start);
    $title = sprintf('Successfully deployed to %s!', get('hostname'));
    $messages = [
        sprintf('Successfully deployed %s (%s) to %s!', get('branch'), get('release.summary'), get('hostname')),
        sprintf('Total Time Elapsed: %sm %ss', $diff->i, $diff->s)
    ];
    exec(sprintf('export DISPLAY=:0; notify-send "%s" "%s"', $title, implode("\n", $messages)));
});

after('success', 'notification:system');

// Ask Tag/Branch questions before deployment and setup Slack variables
task('deploy:before', function () {
    /** @var Host $server */
    $server = \Deployer\Task\Context::get()->getHost();

    if (!$server instanceof Host) {
        output()->writeln(sprintf('<error>Server not instance of %s, can not reliably determine user</error>', Host::class));

        return;
    }

    $user = $server->getUser();
    $input = input();
    $output = output();

    $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

    if ($branch = $input->getOption('branch')) {
        // Slack BC
        set('branch', $branch);
        set('release.summary', sprintf('Branch %s', $branch)); // todo get last ref for branch from git?
    } elseif ($tag = $input->getOption('tag')) {
        // Slack BC
        set('branch', $tag);
        set('release.summary', sprintf('Tag %s', $tag)); // todo get msg for tag from git?
    } elseif ('b' == $input->getOption('deploy-ask')) {
        requireBranch($input, $output);
    } elseif ('t' == $input->getOption('deploy-ask')) {
        requireTag($input, $output);
    } else {
        switch ($server->getConfig()->get('type')) {
            case 'branch':
                requireBranch($input, $output);
                break;
            case 'tag':
                requireTag($input, $output);
                break;
            default:
                requireTag($input, $output);
        }
    }

    $env = $server->getConfig()->get('symfony_env');

    if (!$env) {
        throw new \Exception(sprintf('<error>Environment for Host "%s" is not configured</error>', $user));
    }

    if ($input->getOption('symfony_env')) {
        $env = $input->getOption('symfony_env');
    }

    set('symfony_env', $env);
    set('env', [
        'SYMFONY_ENV' => $env
    ]);

    // Slack BC
    set('server.user', $user);

    // Slack BC
    $git = trim(runLocally('which git'));
    $email = trim(runLocally($git . ' config --get user.email'));
    set('user.email', $email);
    $user = trim(runLocally($git . ' config --get user.name'));
    set('user.name', $user);

    // Slack BC
    $revision = trim(runLocally('git log -n 1 --format="%h"'));
    set('git.revision', $revision);

    if (0 !== strcmp('prod', $user)) {
        // if not prod, don't clear controllers
        set('clear_paths', []);
        // and also required dev @ composer
        set('composer_options', 'install --verbose --prefer-dist --optimize-autoloader');
    }

    // Output Release
    $release = get('release_name');
    $output->writeln(sprintf('Release #<info>%s</info>', $release));
})->setPrivate();

function requireTag(InputInterface $input, OutputInterface $output): string {
    $git = trim(runLocally('which git'));

    if (!$git) {
        throw new \Exception('Could not find local git');
    }

    $command = sprintf("%s for-each-ref refs/tags --sort=-taggerdate --format='%%(refname)\t%%(subject)' --count=6", $git);
    $gitOutput = runLocally($command);

    $allTags = [];
    $choices = [];
    $tags = explode("\n", $gitOutput);
    foreach ($tags as $key => $tag) {
        if ($tag) {
            [$tag, $message] = explode("\t", $tag);
            $tag = str_replace('refs/tags/', '', $tag);
            $allTags[$key + 1] = $tag;
            $choices[$tag] = $message;
        }
    }

    $helper = new QuestionHelper();
    $question = new ChoiceQuestion('You must select a tag do deploy.', $choices);

    $question->setNormalizer(function ($answer) use ($allTags) {
        if (in_array($answer, $allTags)) {
            return $answer;
        }

        if (isset($allTags[$answer])) {
            return $allTags[$answer];
        }

        throw new \RuntimeException(sprintf('You must select a tag from the list or run this command with the --tag option.'));
    });

    $tag = $helper->ask($input, $output, $question);

    $input->setOption('tag', $tag);

    // Slack BC
    set('branch', $tag);
    set('release.summary', $choices[$tag]);

    $output->writeln(sprintf('Will deploy git tag <info>%s</info>', $tag));

    return $tag;
}

function requireBranch(InputInterface $input, OutputInterface $output) {
    $git = trim(runLocally('which git'));

    if (!$git) {
        throw new \Exception('Could not find local git');
    }

    $command = sprintf("%s for-each-ref --sort=-committerdate refs/heads/ --format='%%(refname)\t%%(committerdate:relative)\t%%(subject)' --count=9", $git);
    $result = runLocally($command);
    $gitOutput = $result;

    $allBranches = [];
    $choices = [];
    $branches = explode("\n", $gitOutput);
    foreach ($branches as $key => $branch) {
        if ($branch) {
            [$branch, $date, $message] = explode("\t", $branch);
            $branch = str_replace('refs/heads/', '', $branch);
            $allBranches[$key + 1] = $branch;
            $choices[$branch] = sprintf('%s - %s', $message, $date);
        }
    }

    $helper = new QuestionHelper();
    $question = new ChoiceQuestion('You must select a branch do deploy.', $choices);

    $question->setNormalizer(function ($answer) use ($allBranches) {
        if (in_array($answer, $allBranches)) {
            return $answer;
        }

        if (isset($allBranches[$answer])) {
            return $allBranches[$answer];
        }

        throw new \RuntimeException(sprintf('You must select a branch from the list or run this command with the --branch option.'));
    });

    $branch = $helper->ask($input, $output, $question);

    $input->setOption('branch', $branch);

    // Slack BC
    set('branch', $branch);
    set('release.summary', $choices[$branch]);

    $output->writeln(sprintf('Will deploy git branch <info>%s</info>', $branch));

    return $branch;
}

before('deploy', 'deploy:before');

// Always SYMLINK Assets
task('deploy:assets:install', function () {
    run('{{bin/php}} {{bin/console}} assets:install {{console_options}} --symlink {{release_path}}/web');
})->desc('Install bundle assets');

task('status', function () {
    // todo last commit message on branch, message on tag
    $output = run('cd {{current_path}} && ({{bin/git}} describe --all --contains HEAD)');
    $message = sprintf('Currently installed on %s@%s: <info>%s</info>', get('user'), get('hostname'), $output);
    output()->writeln($message);
});