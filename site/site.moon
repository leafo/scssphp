require "sitegen"

sitegen.create_site =>
  @version = "0.0.1"
  deploy_to "leaf@leafo.net", "www/aroma/"
