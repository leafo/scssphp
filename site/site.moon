require "sitegen"

sitegen.create_site =>
  @current_version = "0.0.1"
  @title = "SCSS Compiler in PHP"
  deploy_to "leaf@leafo.net", "www/aroma/"
