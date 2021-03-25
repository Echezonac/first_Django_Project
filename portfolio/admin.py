from django.contrib import admin
from .models import carousel
from .models import gallery
from .models import googlemap
from .models import archProject
from .models import techProject
from .models import bizProject
from .models import client

admin.site.register(carousel)
admin.site.register(gallery)
admin.site.register(googlemap)
admin.site.register(archProject)
admin.site.register(techProject)
admin.site.register(bizProject)
admin.site.register(client)
