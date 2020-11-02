# GitHub Action used for teams management

[![Actions Status](https://github.com/navikt/teams-action/workflows/Test%20and%20publish/badge.svg)](https://github.com/navikt/teams-action/actions)

This action can be used to create Azure AD groups and GitHub teams for all teams at NAV. The action is used by [navikt/teams](https://github.com/navikt/teams).

When the action is triggered it will open the teams YAML file pointed to by the `TEAMS_YAML_PATH` environment variable, loop over all teams, and for each team it will do the following:

- If the team already exists as a group in Azure AD, skip to the next team
- If the team already exists as a team on GitHub, skip to the next one
- Create a mail and security enabled group in Azure AD for the team
- Create a GitHub team for the team

## Required environment variables

The action requires the following environment variables to be set (using for instance `env` in a GitHub workflow):

### `AZURE_AD_APP_ID`

The Azure AD application ID (client ID) of the application used to access the Microsoft Graph API. The application must have the following API permissions:

- `Application.ReadWrite.All`
- `Group.ReadWrite.All`

### `AZURE_AD_APP_SECRET`

The Azure AD application secret of the application used to access the Microsoft Graph API.

### `AZURE_AD_CONTAINER_APP_ID`

Each group that is created by this action will be added to this enterprise application. This is the object ID of the application. This application is used to easily identify teams managed by this solution.

### `AZURE_AD_CONTAINER_APP_ROLE_ID`

When adding groups to the enterprise application they will receive this role.

### `GITHUB_PAT`

The personal access token used to access the GitHub API. The token must have the following scopes:

- `admin:org`

The token must also be [enabled for SSO](https://help.github.com/en/github/authenticating-to-github/authorizing-a-personal-access-token-for-use-with-saml-single-sign-on).

The reason for using a personal access token instead of the auto-generated `GITHUB_TOKEN` is because the [Team synchronization](https://developer.github.com/v3/teams/team_sync/) API does not support GitHub App authentication yet. Once this is supported the `GITHUB_PAT` secret can be superseded by the auto generated `GITHUB_TOKEN`.

### `TEAMS_YAML_PATH`

Path to where the teams YAML file is located. Documentation regarding this file is available in the [navikt/teams](https://github.com/navikt/teams) repository.

### `NAIS_DEPLOYMENT_API_SECRET`

Hex-encoded secret used to create Nais deployment key for the teams.

### `COMMITTER`

GitHub username of the user who committed the change. This user will be added as an owner to the created Azure AD group.

## Optional environment variables

### `AAD_OWNER_GROUPS`

Comma separated list of Azure AD group object IDs to add the user object that is connected to `COMMITTER` to.

## Release a new version

There is a workflow that runs when a tag starting with `v` is pushed, which publishes a docker image to [dockerhub](https://hub.docker.com/repository/docker/navikt/teams-action).