# GitHub Action used for teams management

This action can be used to create Azure AD groups and GitHub teams for all teams at NAV. The action is used by [navikt/teams](https://github.com/navikt/teams).

When the action is triggered it will open the teams YAML file pointed to by the `TEAMS_YAML_PATH` environment variable, loop over all teams, and for each team it will do the following:

- If the team already exists as a group in Azure AD, skip to the next team
- If the team already exists as a team on GitHub, skip to the next one
- Create a mail and security enabled group in Azure AD for the team
- Add the newly created Azure AD group to the Google Suite Provisioning application so it's synced to Google
- Create a GitHub team for the team

## Required environment variables

The action requires the following environment variables to be set (using for instance `env` in a GitHub workflow):

### `AZURE_AD_APP_ID`

The Azure AD application ID (client ID) of the application used to access the Microsoft Graph API. The application must have the following API permissions:

- `Application.ReadWrite.All`
- `Group.ReadWrite.All`

### `AZURE_AD_APP_SECRET`

The Azure AD application secret of the application used to access the Microsoft Graph API.

### `AZURE_AD_GOOGLE_PROVISIONING_APP_ID`

Each group that is created by this action will be added to this enterprise application. This is the object ID of the application.

### `AZURE_AD_GOOGLE_PROVISIONING_ROLE_ID`

When adding groups to the enterprise application they will receive this role.

### `GITHUB_PAT`

The personal access token used to access the GitHub API. The token must have the following scopes:

- `admin:org`

The token must also be [enabled for SSO](https://help.github.com/en/github/authenticating-to-github/authorizing-a-personal-access-token-for-use-with-saml-single-sign-on).

The reason for using a personal access token instead of the auto-generated `GITHUB_TOKEN` is because the [Team synchronization](https://developer.github.com/v3/teams/team_sync/) API does not support GitHub App authentication yet. Once this is supported the `GITHUB_PAT` secret can be superseded by the auto generated `GITHUB_TOKEN`.

### `TEAMS_YAML_PATH`

Path to where the teams YAML file is located. Documentation regarding this file is available in the [navikt/teams](https://github.com/navikt/teams) repository.
