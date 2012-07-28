require "sitegen"
site = sitegen.create_site =>
  @title = "Hello World"
site\write!
