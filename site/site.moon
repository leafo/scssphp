require "sitegen"

sitegen.create_site =>
  @current_version = "0.0.2"
  @title = "SCSS Compiler in PHP"

  deploy_to "leaf@leafo.net", "www/scssphp/"

  add "docs/index.md"

